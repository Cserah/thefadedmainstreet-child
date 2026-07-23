"""Bookended WP Pusher deploy for thefadedmainstreet-child (Lightsail, AWS WordPress blueprint).

Same flow as the ApartmentABC pipeline: WP Pusher must NEVER be left active
(its PHP deprecation warnings have fatal-ed wp-admin before). Every deploy is
bookended:
  activate wppusher -> trigger the Push-to-Deploy webhook -> poll live version ->
  verify homepage + wp-admin (the canary) -> deactivate wppusher.
The plugin is left DEACTIVATED.

NOTE: this instance uses the new AWS-packaged Lightsail WordPress blueprint,
not Bitnami: SSH user is `admin`, WordPress lives at /var/www/html.

The theme is deployed by WP Pusher as a ZIP download (the live theme dir is NOT
a git repo), so a plain git pull cannot deploy - the webhook is the only trigger.
The webhook secret is fetched from the server at run time (never hardcoded):
  {site}/?wppusher-hook&token=<wppusher_token option>&package=<base64(stylesheet)>

Usage:  python deploy.py         (deploys the version at the local repo's HEAD)
"""
import base64
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

KEY = "C:/Zoltan/Keys/thefadedmainstreet.pem"
SSH_TARGET = "admin@3.90.133.227"
SITE = "https://thefadedmainstreet.com"
THEME = "thefadedmainstreet-child"
STYLE_URL = f"{SITE}/wp-content/themes/{THEME}/style.css"
ADMIN_URL = f"{SITE}/wp-admin/"
REPO = r"C:\Zoltan\thefadedmainstreet"
WP = "sudo wp --path=/var/www/html --allow-root"
POLL_INTERVAL = 10
POLL_TIMEOUT = 120

T0 = time.time()


def el():
    return f"t+{int(time.time()-T0)}s"


def ssh(cmd, timeout=90):
    p = subprocess.run(
        ["ssh", "-i", KEY, "-o", "ConnectTimeout=25",
         "-o", "StrictHostKeyChecking=accept-new", SSH_TARGET, cmd],
        capture_output=True, text=True, timeout=timeout)
    out = "\n".join(l for l in (p.stdout or "").splitlines()
                    if "already defined" not in l and l.strip())
    return p.returncode, out.strip(), (p.stderr or "").strip()


def http(url, timeout=150, follow=True):
    """Return (status_code or None, body_text or None). Follows redirects when
    follow=True (so /wp-admin/ -> wp-login.php resolves to 200 if WP is alive)."""
    class _NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None
    opener = (urllib.request.build_opener() if follow
              else urllib.request.build_opener(_NoRedirect))
    req = urllib.request.Request(url, headers={
        "User-Agent": "thefadedmainstreet-deploy",
        "Cache-Control": "no-cache", "Pragma": "no-cache"})
    try:
        with opener.open(req, timeout=timeout) as r:
            return r.getcode(), r.read().decode("utf-8", "ignore")
    except urllib.error.HTTPError as e:
        return e.code, None
    except Exception as e:
        return None, str(e)


def version_of(text):
    m = re.search(r"Version:\s*([0-9][0-9.]*)", text or "")
    return m.group(1) if m else None


def live_version(timeout=30):
    """Fetch the live theme version. MUST cache-bust: Cloudflare caches the bare
    style.css (Cf-Cache-Status HIT), so the plain URL stays stale after a
    deploy. A unique query string misses the edge cache -> origin."""
    code, body = http(f"{STYLE_URL}?cb={int(time.time()*1000)}", timeout=timeout)
    return code, version_of(body)


def repo_head_version():
    p = subprocess.run(["git", "-C", REPO, "show", "HEAD:style.css"],
                       capture_output=True, text=True)
    return version_of(p.stdout)


def build_webhook():
    rc, out, err = ssh(f"{WP} option get wppusher_token")
    tok = re.search(r"[0-9a-f]{32,}", out)
    if not tok:
        raise RuntimeError(f"could not read wppusher_token (rc={rc}): {out} {err}")
    token = tok.group(0)
    package = base64.b64encode(THEME.encode()).decode()
    url = (f"{SITE}/?wppusher-hook&token={token}"
           f"&package={urllib.parse.quote(package)}")
    return url, url.replace(token, token[:6] + "..." + token[-4:])


def deactivate():
    rc, out, err = ssh(f"{WP} plugin deactivate wppusher")
    print(f"[{el()}] deactivate wppusher: {out or err}")


def main():
    target = repo_head_version()
    print(f"[{el()}] target version (repo HEAD style.css): {target}")
    if not target:
        sys.exit("ERROR: could not read target version from repo HEAD")

    code, old = live_version()
    print(f"[{el()}] pre-check: live style.css HTTP {code}, version {old}")
    if code != 200:
        sys.exit(f"ERROR: live site not 200 (got {code}); aborting")
    if old == target:
        print(f"[{el()}] live already at {target}; ensuring wppusher is deactivated")
        deactivate()
        return

    activated = False
    reached = False
    try:
        rc, out, err = ssh(f"{WP} plugin activate wppusher")
        activated = True
        print(f"[{el()}] activate wppusher: {out or err}")

        # canary: wp-admin must not fatal right after activation
        acode, _ = http(ADMIN_URL, timeout=40)
        print(f"[{el()}] canary wp-admin after activate: HTTP {acode}")
        if acode is None or acode >= 500:
            print(f"[{el()}] wp-admin unhealthy ({acode}) -> deactivating and stopping")
            return

        url, safe = build_webhook()
        print(f"[{el()}] triggering webhook: {safe}")
        wcode, _ = http(url, timeout=170)
        print(f"[{el()}] webhook returned HTTP {wcode}")

        deadline = time.time() + POLL_TIMEOUT
        while time.time() < deadline:
            time.sleep(POLL_INTERVAL)
            c, v = live_version()
            print(f"[{el()}]   poll: HTTP {c}, live version {v}")
            if v == target:
                reached = True
                break
        if not reached:
            print(f"[{el()}] TIMEOUT: live version never reached {target}")

        hc, _ = http(SITE + "/", timeout=40)
        ac, _ = http(ADMIN_URL, timeout=40)
        print(f"[{el()}] verify: homepage HTTP {hc}, wp-admin HTTP {ac}")
    finally:
        if activated:
            deactivate()  # ALWAYS, even on timeout/exception

    fc, fv = live_version()
    hc2, _ = http(SITE + "/", timeout=40)
    print(f"[{el()}] final (wppusher off): style.css HTTP {fc} v{fv}, homepage HTTP {hc2}")
    status = "SUCCESS" if (reached and fv == target) else "STUCK/FAILED"
    print(f"[{el()}] RESULT: {status}  {old} -> {fv}  (target {target})  in {int(time.time()-T0)}s")
    if status != "SUCCESS":
        sys.exit(1)


if __name__ == "__main__":
    main()
