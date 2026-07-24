<?php
/**
 * Homepage — direction 1A "The Broadsheet".
 * Bands: top bar -> masthead rule -> hero -> featured story -> recent stories ->
 * recurring themes -> The Map -> subscribe band -> footer.
 * Blocksy's own header/footer are hidden on this page via style.css.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$fms_assets  = get_stylesheet_directory_uri() . '/assets';
$fms_channel = 'https://www.youtube.com/@thefadedmainstreet';

/* Featured story = newest post; recent = the next three. */
$fms_q = new WP_Query( array(
	'post_type'           => 'post',
	'posts_per_page'      => 4,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
) );
$fms_posts    = $fms_q->posts;
$fms_featured = array_shift( $fms_posts );

$fms_story_count = (int) wp_count_posts()->publish;

/** YouTube thumb for a post, or its featured image, or null. */
function fms_post_still( $post_id ) {
	$vid = get_post_meta( $post_id, 'fms_youtube_id', true );
	if ( $vid ) {
		return sprintf( 'https://i.ytimg.com/vi/%s/hqdefault.jpg', $vid );
	}
	return get_the_post_thumbnail_url( $post_id, 'large' ) ?: null;
}
?>

<main id="main" class="fms-home">

	<?php fms_topbar(); ?>

	<section class="fms-hero">
		<img class="fms-hero__img"
			src="<?php echo esc_url( $fms_assets . '/hero-ghost-sign-neutral-21x9.jpg' ); ?>"
			srcset="<?php echo esc_url( $fms_assets . '/hero-ghost-sign-neutral-960.jpg' ); ?> 960w,
				<?php echo esc_url( $fms_assets . '/hero-ghost-sign-neutral-21x9.jpg' ); ?> 1920w"
			sizes="100vw"
			alt="A faded hand-painted flour advertisement on an aged brick wall at golden hour"
			width="1920" height="815" fetchpriority="high" decoding="async" />
		<div class="fms-hero__grade" aria-hidden="true"></div>
		<div class="fms-hero__tint" aria-hidden="true"></div>
		<div class="fms-hero__text">
			<span class="fms-hero__script">Faded Main Street</span>
			<h2 class="fms-hero__title">The stories of vanished America</h2>
			<p class="fms-hero__dek">Ghost signs, lost buildings, and forgotten places &mdash; filmed, researched, and written down before they're gone for good.</p>
			<div class="fms-hero__btns">
				<a class="fms-btn" href="<?php echo esc_url( $fms_channel ); ?>">&#9654;&nbsp; Watch the channel</a>
				<a class="fms-btn fms-btn--ghost" href="#stories">Read the stories</a>
			</div>
		</div>
	</section>

	<?php if ( $fms_featured ) :
		$f_still = fms_post_still( $fms_featured->ID );
		$f_cats  = get_the_category( $fms_featured->ID );
		$f_kick  = $f_cats ? strtoupper( $f_cats[0]->name ) : 'THE STORY';
	?>
	<section class="fms-band fms-featured" id="stories">
		<div class="fms-wrap">
			<span class="fms-eyebrow">The Latest Story</span>
			<div class="fms-featured__grid">
				<a class="fms-still" href="<?php echo esc_url( get_permalink( $fms_featured ) ); ?>">
					<?php if ( $f_still ) : ?>
						<img src="<?php echo esc_url( $f_still ); ?>"
							alt="<?php echo esc_attr( get_the_title( $fms_featured ) ); ?>"
							width="960" height="540" loading="lazy" decoding="async" />
					<?php endif; ?>
					<span class="fms-still__shade" aria-hidden="true"></span>
					<span class="fms-still__badge">&#9654; Video + Essay</span>
					<span class="fms-still__play" aria-hidden="true">&#9654;</span>
				</a>
				<div>
					<span class="fms-kicker"><?php echo esc_html( $f_kick ); ?></span>
					<h3 class="fms-featured__title">
						<a style="text-decoration:none;color:inherit;" href="<?php echo esc_url( get_permalink( $fms_featured ) ); ?>"><?php echo esc_html( get_the_title( $fms_featured ) ); ?></a>
					</h3>
					<p class="fms-featured__body"><?php echo esc_html( wp_trim_words( get_the_excerpt( $fms_featured ), 40 ) ); ?></p>
					<p>
						<a class="fms-btn" style="font-size:12px;" href="<?php echo esc_url( get_permalink( $fms_featured ) ); ?>">Watch &amp; read &#9656;</a>
						<span class="fms-featured__meta">Published <?php echo esc_html( get_the_date( 'M Y', $fms_featured ) ); ?></span>
					</p>
				</div>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="fms-band fms-recent">
		<div class="fms-wrap">
			<div class="fms-recent__head">
				<h3>Recent stories</h3>
				<a class="fms-archive-link" href="<?php echo esc_url( $fms_channel ); ?>">The Full Archive &rarr;</a>
			</div>
			<?php if ( $fms_posts ) : ?>
				<div class="fms-cards">
					<?php foreach ( $fms_posts as $p ) :
						$still = fms_post_still( $p->ID );
						$cats  = get_the_category( $p->ID );
						$kick  = $cats ? strtoupper( $cats[0]->name ) : 'STORY'; ?>
						<a class="fms-card" href="<?php echo esc_url( get_permalink( $p ) ); ?>">
							<span class="fms-card__media">
								<?php if ( $still ) : ?>
									<img src="<?php echo esc_url( $still ); ?>"
										alt="<?php echo esc_attr( get_the_title( $p ) ); ?>"
										width="480" height="270" loading="lazy" decoding="async" />
								<?php endif; ?>
								<?php if ( get_post_meta( $p->ID, 'fms_youtube_id', true ) ) : ?>
									<span class="fms-card__chip">&#9654; Video</span>
								<?php endif; ?>
							</span>
							<span class="fms-card__kicker"><?php echo esc_html( $kick ); ?></span>
							<h4><?php echo esc_html( get_the_title( $p ) ); ?></h4>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $p ), 22 ) ); ?></p>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p>New stories are on the way. In the meantime, the full archive lives on
					<a href="<?php echo esc_url( $fms_channel ); ?>">the Faded Main Street YouTube channel</a>.</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="fms-band fms-themes" id="themes">
		<div class="fms-wrap">
			<span class="fms-eyebrow">Recurring Themes</span>
			<h3>What we keep coming back to</h3>
			<div class="fms-themes__grid">
				<div class="fms-theme">
					<span class="fms-theme__no">i.</span>
					<h4>Ghost signs</h4>
					<p>Hand-painted brick advertisements faded to a whisper &mdash; the last billboards of businesses gone eighty years.</p>
				</div>
				<div class="fms-theme">
					<span class="fms-theme__no">ii.</span>
					<h4>Lost buildings</h4>
					<p>Opera houses, depots, and department stores that anchored a town &mdash; and what stands in their place.</p>
				</div>
				<div class="fms-theme">
					<span class="fms-theme__no">iii.</span>
					<h4>Forgotten brands</h4>
					<p>The names in every pantry and toolbox of their day, and how a household word becomes a trivia answer.</p>
				</div>
				<div class="fms-theme">
					<span class="fms-theme__no">iv.</span>
					<h4>Roadside relics</h4>
					<p>Motels, diners, and filling stations the interstate passed by &mdash; still standing, barely.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="fms-band fms-map" id="map">
		<div class="fms-wrap">
			<div class="fms-map__grid">
				<div class="fms-map__surface" role="img"
					aria-label="<?php esc_attr_e( 'Map of documented places across the United States', 'thefadedmainstreet-child' ); ?>">
					<span class="fms-pin" style="top:34%;left:62%;"></span>
					<span class="fms-pin" style="top:52%;left:44%;"></span>
					<span class="fms-pin" style="top:64%;left:70%;"></span>
					<span class="fms-pin" style="top:42%;left:26%;"></span>
					<span class="fms-pin fms-pin--sm" style="top:70%;left:36%;"></span>
					<span class="fms-pin fms-pin--sm" style="top:26%;left:50%;"></span>
				</div>
				<div>
					<span class="fms-eyebrow">The Map</span>
					<h3 class="fms-map__title">Every place we've documented</h3>
					<p class="fms-map__body">Each story starts with a place. The map keeps track of every town, sign, and building we've filmed &mdash; and where we're headed next.</p>
					<div class="fms-stats">
						<div class="fms-stat"><b><?php echo esc_html( max( 1, $fms_story_count ) ); ?></b><span><?php echo esc_html( _n( 'Story', 'Stories', max( 1, $fms_story_count ), 'thefadedmainstreet-child' ) ); ?></span></div>
						<div class="fms-stat"><b>4</b><span>Themes</span></div>
					</div>
					<p><a class="fms-btn" href="<?php echo esc_url( $fms_channel ); ?>">Explore the map &#9656;</a></p>
				</div>
			</div>
		</div>
	</section>

	<section class="fms-subscribe">
		<span class="fms-subscribe__script">Never miss a street</span>
		<h3>Subscribe on YouTube</h3>
		<p>New documentaries most weeks. Join the crew tracking down what's left of Main Street.</p>
		<a class="fms-btn" href="<?php echo esc_url( $fms_channel ); ?>">&#9654;&nbsp; Subscribe on YouTube</a>
	</section>

	<?php fms_broadsheet_footer(); ?>

</main>

<?php get_footer(); ?>
