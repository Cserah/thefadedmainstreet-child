"""CI bookended WP Pusher deploy for thefadedmainstreet-child (GitHub Actions).

Server-side twin of deploy.py, but env-driven so it runs on a GitHub Actions
runner. Triggered by .github/workflows/deploy.yml on every push to main.

NOTE: this instance uses the new AWS-packaged Lightsail WordPress blueprint,
not Bitnami: SSH user is `admin`, WordPress lives at /var/www/html.

WP Pusher's PHP deprecation warnings have fatal-ed wp-admin before, so the
plugin is NEVER left active. Every deploy is bookended:
  activate wppusher -> trigger the Push-to-Deploy webhook -> poll live version ->
  verify homepage + wp-admin (canary) -> deactivate wppusher (always).

The theme is deployed by WP Pusher as a ZIP (the live theme dir is NOT a git
repo), so the webhook is the only trigger. The webhook secret is fetched from
the server at run time (never hardcoded, never printed in full).

Env (set by the workflow):
  SSH_TARGET  admin@<host>                     (from the LIGHTSAIL_HOST secret)
  KEY_PATH    path to the private key          (written from the LIGHTSAIL_SSH_KEY secret)
  SITE        https://thefadedmainstreet.com   (optional)
  THEME       thefadedmainstreet-child         (optional)
"""
import base64
import os
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

KEY = os.environ["KEY_PATH"]
SSH_TARGET = os.environ["SSH_TARGET"]
SITE = os.environ.get("SITE", "https://thefadedmainstreet.com").rstrip("/")
THEME = os.environ.get("THEME", "thefadedmainstreet-child")
STYLE_URL = f"{SITE}/wp-content/themes/{THEME}/style.css"
ADMIN_URL = f"{SITE}/wp-admin/"
WP = "sudo wp --path=/var/www/html --allow-root"
POLL_INTERVAL = 10
POLL_TIMEOUT = 150

T0 = time.time()


def el():
    return f"t+{int(time.time()-T0)}s"


def ssh(cmd, timeout=90):
    p = subprocess.run(
        ["ssh", "-i", KEY, "-o", "ConnectTimeout=25",
         "-o", "StrictHostKeyChecking=accept-new",
         "-o", "UserKnownHostsFile=/dev/null", SSH_TARGET, cmd],
        capture_output=True, text=True, timeout=timeout)
    out = "\n".join(l for l in (p.stdout or "").splitlines()
                    if "already defined" not in l and l.strip())
    return p.returncode, out.strip(), (p.stderr or "").strip()


def http(url, timeout=170, follow=True):
    class _NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None
    opener = (urllib.request.build_opener() if follow
              else urllib.request.build_opener(_NoRedirect))
    req = urllib.request.Request(url, headers={
        "User-Agent": "thefadedmainstreet-ci-deploy",
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
    # Cache-bust: Cloudflare caches the bare style.css.
    code, body = http(f"{STYLE_URL}?cb={int(time.time()*1000)}", timeout=timeout)
    return code, version_of(body)


def repo_head_version():
    # The runner has the pushed commit checked out; read style.css directly.
    with open("style.css", encoding="utf-8") as f:
        return version_of(f.read())


def build_webhook():
    rc, out, err = ssh(f"{WP} option get wppusher_token")
    tok = re.search(r"[0-9a-f]{32,}", out)
    if not tok:
        raise RuntimeError(f"could not read wppusher_token (rc={rc}): {err}")
    token = tok.group(0)
    package = base64.b64encode(THEME.encode()).decode()
    url = f"{SITE}/?wppusher-hook&token={token}&package={urllib.parse.quote(package)}"
    safe = url.replace(token, token[:6] + "..." + token[-4:])
    return url, safe


def deactivate():
    rc, out, err = ssh(f"{WP} plugin deactivate wppusher")
    print(f"[{el()}] deactivate wppusher: {out or err}")


def main():
    target = repo_head_version()
    print(f"[{el()}] target version (checked-out style.css): {target}")
    if not target:
        sys.exit("ERROR: could not read target version from style.css")

    code, old = live_version()
    print(f"[{el()}] pre-check: live style.css HTTP {code}, version {old}")
    if code != 200:
        sys.exit(f"ERROR: live site not 200 (got {code}); aborting")
    if old == target:
        print(f"[{el()}] live already at {target}; nothing to deploy. "
              f"(Bump the Version: in style.css to trigger a deploy.)")
        deactivate()  # ensure wppusher is off regardless
        return

    activated = False
    reached = False
    try:
        rc, out, err = ssh(f"{WP} plugin activate wppusher")
        activated = True
        print(f"[{el()}] activate wppusher: {out or err}")

        acode, _ = http(ADMIN_URL, timeout=40)
        print(f"[{el()}] canary wp-admin after activate: HTTP {acode}")
        if acode is None or acode >= 500:
            print(f"[{el()}] wp-admin unhealthy ({acode}) -> deactivating and stopping")
            sys.exit(1)

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
