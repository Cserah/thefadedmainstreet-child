<?php
/**
 * Faded Main Street — Blocksy child theme.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'FMS_VERSION', wp_get_theme()->get( 'Version' ) );

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
 * Organization JSON-LD in <head>.
 * sameAs: YouTube is live now; the rest are placeholders — uncomment each line
 * below as the profile goes live.
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
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => 'Faded Main Street',
		'url'         => 'https://thefadedmainstreet.com',
		'logo'        => get_stylesheet_directory_uri() . '/assets/profile-icon.jpg',
		'description' => 'Faded Main Street is a documentary YouTube channel about vanished America — ghost signs, lost buildings, and forgotten places, told one story at a time.',
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
