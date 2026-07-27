<?php
/**
 * Faded Main Street — Blocksy child theme.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'FMS_VERSION', wp_get_theme()->get( 'Version' ) );

/**
 * Cloudflare Flexible SSL: WP's site URL is http at the origin, so theme URIs
 * come out as http://. Cloudflare rewrites src attributes to https but NOT
 * srcset, which mixed-content-blocks <picture>/<img srcset> images. Force
 * https on all theme asset URIs.
 */
foreach ( array( 'stylesheet_directory_uri', 'template_directory_uri', 'stylesheet_uri' ) as $fms_uri_filter ) {
	add_filter( $fms_uri_filter, function ( $uri ) {
		return set_url_scheme( $uri, 'https' );
	} );
}

/** Parent + child styles, Google Fonts (Playfair Display, Lora, Pinyon Script). */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'blocksy-parent', get_template_directory_uri() . '/style.css', array(), null );
	wp_enqueue_style(
		'fms-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Grand+Hotel&family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Oswald:wght@300..600&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'fms-child',
		get_stylesheet_uri(),
		array( 'blocksy-parent', 'fms-fonts' ),
		FMS_VERSION
	);
} );

/** Google Analytics 4 measurement ID. Declared once; never inline the literal. */
define( 'FMS_GA4_ID', 'G-85J8WJF86K' );

/**
 * Google Analytics 4.
 *
 * Priority 1 so the tag lands as early in <head> as possible, ahead of the
 * favicon (4), Organization JSON-LD (5) and Article JSON-LD (20) hooks.
 *
 * Skipped for logged-in users who can edit posts. The site currently has
 * near-zero traffic, so the author's own admin sessions would otherwise
 * dominate the numbers. Note this deliberately still tracks logged-in
 * subscribers, should any ever exist — only editors and above are excluded.
 */
add_action( 'wp_head', function () {
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}
	$id = FMS_GA4_ID;
	?>
<!-- Google tag (gtag.js) -->
<script async src="<?php echo esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . $id ); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo esc_js( $id ); ?>');
</script>
	<?php
}, 1 );

/** Favicon set (generated from the F monogram). */
add_action( 'wp_head', function () {
	$a = get_stylesheet_directory_uri() . '/assets';
	echo '<link rel="icon" href="' . esc_url( $a . '/favicon.ico' ) . '" sizes="32x32">' . "\n";
	echo '<link rel="icon" type="image/png" href="' . esc_url( $a . '/favicon-192.png' ) . '" sizes="192x192">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $a . '/apple-touch-icon.png' ) . '">' . "\n";
}, 4 );

/** Preconnect for the font CDN (Core Web Vitals). */
add_filter( 'wp_resource_hints', function ( $urls, $relation ) {
	if ( 'preconnect' === $relation ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
	}
	return $urls;
}, 10, 2 );

/**
 * Stable @id for the publisher entity. Article nodes reference this instead of
 * repeating an inline publisher, so crawlers merge the two into one
 * Organization rather than treating "Faded Main Street" and "The Faded Main
 * Street" as separate entities. Derived from home_url() so it follows the site
 * rather than hardcoding a host.
 */
function fms_org_id() {
	return home_url( '/' ) . '#organization';
}

/**
 * Organization JSON-LD in <head>.
 * sameAs: YouTube is live now; the rest are placeholders — uncomment each line
 * below as the profile goes live.
 *
 * The em dash in the description is written as a \u{2014} escape in a
 * double-quoted string rather than a literal character, so the value cannot be
 * corrupted by an editor or tool that mishandles the file's encoding.
 */
add_action( 'wp_head', function () {
	$same_as = array(
		'https://www.youtube.com/@thefadedmainstreet',
		// 'https://www.pinterest.com/…',   // Pinterest — fill in when live
		// 'https://medium.com/@…',         // Medium — fill in when live
		// 'https://x.com/…',               // X (Twitter) — fill in when live
		// 'https://www.facebook.com/…',    // Facebook — fill in when live
		// 'https://www.instagram.com/…',   // Instagram — fill in when live
		// 'https://www.tumblr.com/…',      // Tumblr — fill in when live
	);
	$logo   = get_stylesheet_directory_uri() . '/assets/profile-icon.jpg';
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'@id'         => fms_org_id(),
		'name'        => 'Faded Main Street',
		'url'         => home_url( '/' ),
		'logo'        => array(
			'@type' => 'ImageObject',
			'url'   => $logo,
		),
		'image'       => $logo,
		'description' => "Faded Main Street is a documentary YouTube channel about vanished America \u{2014} ghost signs, lost buildings, and forgotten places, told one story at a time.",
		'sameAs'      => array_values( $same_as ),
	);
	echo '<script type="application/ld+json">' .
		wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
		"</script>\n";
}, 5 );

/**
 * Lazy YouTube facade: real thumbnail from i.ytimg.com, iframe injected only
 * on click (no autoplay, no third-party JS before interaction — CWV).
 */
function fms_youtube_facade( $video_id, $title, $eager = false ) {
	$video_id = preg_replace( '/[^A-Za-z0-9_-]/', '', $video_id );
	if ( '' === $video_id ) { return; }
	$thumb = sprintf( 'https://i.ytimg.com/vi/%s/hqdefault.jpg', $video_id );
	?>
	<div class="fms-video-box" data-fms-video="<?php echo esc_attr( $video_id ); ?>">
		<img src="<?php echo esc_url( $thumb ); ?>"
			alt="<?php echo esc_attr( $title ); ?>"
			width="480" height="270"
			<?php echo $eager ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"'; ?> />
		<button class="fms-play" type="button"
			aria-label="<?php echo esc_attr( sprintf( __( 'Play video: %s', 'thefadedmainstreet-child' ), $title ) ); ?>">
			<svg viewBox="0 0 68 48" aria-hidden="true"><path d="M66.5 7.7c-.8-2.9-3-5.1-5.9-5.9C55.5.4 34 .4 34 .4S12.5.4 7.4 1.8c-2.9.8-5.1 3-5.9 5.9C.1 12.8.1 24 .1 24s0 11.2 1.4 16.3c.8 2.9 3 5.1 5.9 5.9C12.5 47.6 34 47.6 34 47.6s21.5 0 26.6-1.4c2.9-.8 5.1-3 5.9-5.9C67.9 35.2 67.9 24 67.9 24s0-11.2-1.4-16.3z" fill="#8a2f22" opacity=".92"/><path d="M27 34.6 44.6 24 27 13.4v21.2z" fill="#f3ead9"/></svg>
		</button>
	</div>
	<?php
}

/**
 * [fms_video id="…" title="…" label="Watch the episode" caption="…"] — the lazy
 * facade, placeable anywhere inside post content. single.php renders the episode
 * video above the article body from the fms_youtube_id meta; this is for
 * articles that want it at a specific point in the copy instead.
 *
 * With a label or caption the facade is wrapped in a framed card, so a reader
 * scanning the page reads it as a video rather than another photograph. Without
 * either it falls back to the plain inline treatment.
 */
add_shortcode( 'fms_video', function ( $atts ) {
	$a = shortcode_atts(
		array( 'id' => '', 'title' => '', 'label' => '', 'caption' => '' ),
		$atts,
		'fms_video'
	);
	if ( '' === $a['id'] ) {
		return '';
	}
	ob_start();
	fms_youtube_facade( $a['id'], $a['title'] ? $a['title'] : get_the_title() );
	$facade = ob_get_clean();

	if ( '' === $a['label'] && '' === $a['caption'] ) {
		return '<div class="fms-inline-video">' . $facade . '</div>';
	}

	$out = '<div class="fms-videocard">';
	if ( '' !== $a['label'] ) {
		$out .= '<p class="fms-videocard__bar">'
			. '<span class="fms-videocard__play" aria-hidden="true">&#9654;</span>'
			. '<span>' . esc_html( $a['label'] ) . '</span></p>';
	}
	$out .= '<div class="fms-videocard__frame">' . $facade . '</div>';
	if ( '' !== $a['caption'] ) {
		$out .= '<p class="fms-videocard__cap">' . esc_html( $a['caption'] ) . '</p>';
	}
	return $out . '</div>';
} );

/** One tiny inline script powers every facade on the page. */
add_action( 'wp_footer', function () {
	?>
	<script>
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.fms-play');
		if (!btn) return;
		var box = btn.closest('[data-fms-video]');
		var id = box.getAttribute('data-fms-video');
		var f = document.createElement('iframe');
		f.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
		f.title = btn.getAttribute('aria-label') || 'YouTube video';
		f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
		f.allowFullscreen = true;
		box.innerHTML = '';
		box.appendChild(f);
	});
	</script>
	<?php
} );

/**
 * Long-form articles (the .ia-wrap generator output) ship fully-formed markup:
 * explicit <p> tags, <section> bands, inline <svg>, and a JSON-LD <script>.
 * wpautop actively damages all of that — it injects <br> between the SVG's
 * child elements, which forces the HTML parser out of foreign content and
 * closes the <svg> early (the timeline then spills out as loose text), and it
 * wraps the JSON-LD script in a stray paragraph. Turn it off for these posts
 * only; ordinary posts written in the editor keep it.
 */
add_action( 'wp', function () {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$post = get_post();
	if ( $post && false !== strpos( $post->post_content, 'class="ia-wrap"' ) ) {
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'shortcode_unautop' );
	}
} );

/**
 * The YouTube ID for a post, read from the same places the facade reads it:
 * the fms_youtube_id meta that single.php renders from, falling back to the id
 * attribute of the [fms_video] shortcode in post content. Deliberately not a
 * hardcoded map — if the facade shows a video, the schema describes that same
 * video, and the two cannot drift apart.
 *
 * Returns '' when the post has no video, which is the signal not to emit a
 * VideoObject at all rather than an empty one.
 */
function fms_post_video_id( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$id = (string) get_post_meta( $post->ID, 'fms_youtube_id', true );
	if ( '' === $id && has_shortcode( $post->post_content, 'fms_video' ) ) {
		$pattern = get_shortcode_regex( array( 'fms_video' ) );
		if ( preg_match( '/' . $pattern . '/s', $post->post_content, $m ) ) {
			$atts = shortcode_parse_atts( $m[3] );
			$id   = isset( $atts['id'] ) ? (string) $atts['id'] : '';
		}
	}
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $id );
}

/**
 * Article JSON-LD lives in post meta, emitted here in wp_head.
 *
 * It used to sit inline in post content, but a <script> tag in the request
 * body trips the WAF in front of this site: the block editor's save POST is
 * blocked before it reaches the origin and Gutenberg reports "Could not get a
 * valid response from the server", making the post uneditable. Keeping the
 * schema out of post_content keeps the editor usable — and wp_head is where
 * structured data belongs anyway.
 *
 * The stored value is decoded and re-encoded rather than printed raw, so a
 * malformed or hostile value can't break out of the <script> element.
 *
 * Anything WordPress already knows is overridden from live data on every
 * render, because a hand-authored blob drifts the moment a post is edited or
 * its publish date moves: the dates and the publisher were both wrong within a
 * day of publishing. Only the fields WordPress cannot derive — headline,
 * description, author, the FAQ entities — come from the stored value.
 */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$raw = get_post_meta( get_the_ID(), 'fms_schema_jsonld', true );
	if ( ! $raw ) {
		return;
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return;
	}

	$graph = isset( $data['@graph'] ) && is_array( $data['@graph'] )
		? $data['@graph']
		: array( $data );

	// The facade injects its iframe with JS, so there is no <iframe> in the
	// served HTML for a crawler to find. Without this node nothing on the page
	// indicates the article has a video at all. Built only when the post
	// actually has one; posts without a video get no VideoObject.
	$video_id   = fms_post_video_id();
	$video_meta = json_decode( (string) get_post_meta( get_the_ID(), 'fms_video_data', true ), true );
	$video_ref  = '';
	if ( '' !== $video_id && is_array( $video_meta ) ) {
		$video_ref = get_permalink() . '#video';
		// maxresdefault does not exist for every upload; the working variant is
		// resolved once at publish time and stored, never guessed at render.
		$variant = ! empty( $video_meta['thumbnail'] ) ? $video_meta['thumbnail'] : 'hqdefault';
	}

	foreach ( $graph as &$node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			continue;
		}
		$types = (array) $node['@type'];
		if ( ! array_intersect( $types, array( 'Article', 'BlogPosting', 'NewsArticle' ) ) ) {
			continue;
		}
		// Live post data wins over whatever was baked into the meta.
		$node['datePublished']    = get_the_date( 'c' );
		$node['dateModified']     = get_the_modified_date( 'c' );
		$node['mainEntityOfPage'] = get_permalink();
		// One publisher entity, referenced by @id, so this and the sitewide
		// Organization block below merge instead of competing.
		$node['publisher']        = array( '@id' => fms_org_id() );
		if ( '' !== $video_ref ) {
			$node['video'] = array( '@id' => $video_ref );
		}
	}
	unset( $node );

	if ( '' !== $video_ref ) {
		$graph[] = array(
			'@type'        => 'VideoObject',
			'@id'          => $video_ref,
			'name'         => $video_meta['name'],
			'description'  => $video_meta['description'],
			'uploadDate'   => $video_meta['uploadDate'],
			'duration'     => $video_meta['duration'],
			'thumbnailUrl' => sprintf( 'https://i.ytimg.com/vi/%s/%s.jpg', $video_id, $variant ),
			'embedUrl'     => 'https://www.youtube-nocookie.com/embed/' . $video_id,
			'contentUrl'   => 'https://www.youtube.com/watch?v=' . $video_id,
		);
	}

	if ( isset( $data['@graph'] ) ) {
		$data['@graph'] = $graph;
	} else {
		$data = $graph[0];
	}

	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
}, 20 );

/* ---------------------------------------------------------------------------
   Shared Broadsheet bands (top bar / masthead / footer), used by the
   homepage and About templates.
--------------------------------------------------------------------------- */
define( 'FMS_CHANNEL_URL', 'https://www.youtube.com/@thefadedmainstreet' );

function fms_topbar() {
	?>
	<div class="fms-topbar">
		<div class="fms-topbar__in">
			<a class="fms-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="fms-logo__stamp" aria-hidden="true">FM</span>
				<span>
					<span class="fms-logo__name">Faded Main Street</span>
					<span class="fms-logo__tag">Stories of Vanished America</span>
				</span>
			</a>
			<nav class="fms-nav" aria-label="<?php esc_attr_e( 'Primary', 'thefadedmainstreet-child' ); ?>">
				<a href="<?php echo esc_url( home_url( '/#stories' ) ); ?>">Stories</a>
				<a href="<?php echo esc_url( home_url( '/#themes' ) ); ?>">Themes</a>
				<a href="<?php echo esc_url( home_url( '/#map' ) ); ?>">The Map</a>
				<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a>
				<a class="fms-btn" href="<?php echo esc_url( FMS_CHANNEL_URL ); ?>">Subscribe &#9656;</a>
			</nav>
		</div>
	</div>
	<div class="fms-masthead">
		Vol. I <span class="d">&#9670;</span> Documented before it disappears <span class="d">&#9670;</span> Est. 2026
	</div>
	<?php
}

function fms_broadsheet_footer() {
	?>
	<footer class="fms-footer">
		<div class="fms-footer__in">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Faded Main Street</span>
			<span>
				<a href="<?php echo esc_url( FMS_CHANNEL_URL ); ?>">YouTube</a>
				<?php /* Uncomment as each goes live:
				<a href="https://www.pinterest.com/…">Pinterest</a>
				<a href="#">Newsletter</a>
				*/ ?>
			</span>
		</div>
	</footer>
	<?php
}

/**
 * Ensure the About page exists (slug "about", assigned to the About template)
 * so the nav and homepage links never 404. Runs once per site.
 */
add_action( 'init', function () {
	if ( get_option( 'fms_about_page_created' ) || wp_installing() ) {
		return;
	}
	if ( ! get_page_by_path( 'about' ) ) {
		wp_insert_post( array(
			'post_title'   => 'About',
			'post_name'    => 'about',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => '',
			'meta_input'   => array( '_wp_page_template' => 'page-about.php' ),
		) );
	}
	update_option( 'fms_about_page_created', 1 );
} );

/** "YouTube video ID" field: plain post meta, editable in the sidebar. */
add_action( 'init', function () {
	register_post_meta( 'post', 'fms_youtube_id', array(
		'type'         => 'string',
		'single'       => true,
		'show_in_rest' => true,
		'auth_callback'=> function () { return current_user_can( 'edit_posts' ); },
		'sanitize_callback' => function ( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $v ); },
	) );
} );
