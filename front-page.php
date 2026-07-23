<?php
/**
 * Homepage — Faded Main Street.
 * Hero (banner artwork) / about / latest videos / recurring themes / follow.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$fms_assets = get_stylesheet_directory_uri() . '/assets';
?>

<main id="main" class="fms-home">

	<section class="fms-hero">
		<img class="fms-hero__img"
			src="<?php echo esc_url( $fms_assets . '/channel-banner-street-corner.jpg' ); ?>"
			alt="A faded painted 'Faded Main Street' sign on an aged brick wall at golden hour"
			width="2752" height="1536" fetchpriority="high" decoding="async" />
		<div class="fms-hero__scrim" aria-hidden="true"></div>
		<div class="fms-hero__inner">
			<span class="fms-hero__kicker">Faded Main Street</span>
			<h1 class="fms-hero__title">The stories of vanished America</h1>
			<p class="fms-hero__tagline">Ghost signs, lost buildings, and forgotten places — documented before they disappear for good.</p>
			<a class="fms-btn fms-btn--solid" href="https://www.youtube.com/@thefadedmainstreet">Watch on YouTube</a>
		</div>
	</section>

	<section class="fms-section fms-about">
		<div class="fms-wrap fms-wrap--narrow">
			<img class="fms-monogram"
				src="<?php echo esc_url( $fms_assets . '/profile-icon.jpg' ); ?>"
				alt="Faded Main Street monogram — a cream script F painted on brick"
				width="84" height="84" loading="lazy" decoding="async" />
			<span class="fms-eyebrow">About the channel</span>
			<h2 class="fms-section-title">Every town had a main street. Most of them faded.</h2>
			<p>Faded Main Street is a documentary channel about the America that's still visible if you know where to look: hand-painted advertisements bleeding through brick, department stores that anchored a downtown for a century, products every household owned and no one remembers buying last.</p>
			<p>Each episode digs into one place, one sign, or one vanished name — who built it, why it mattered, and what's left. The articles on this site accompany each video with the photographs, sources, and details that don't fit on screen.</p>
		</div>
	</section>

	<hr class="fms-rule" />

	<section class="fms-section fms-videos">
		<div class="fms-wrap">
			<span class="fms-eyebrow">Latest episodes</span>
			<h2 class="fms-section-title">Recent stories</h2>

			<?php
			$fms_q = new WP_Query( array(
				'post_type'           => 'post',
				'posts_per_page'      => 6,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			) );
			if ( $fms_q->have_posts() ) : ?>
				<div class="fms-video-grid">
					<?php while ( $fms_q->have_posts() ) : $fms_q->the_post();
						$vid = get_post_meta( get_the_ID(), 'fms_youtube_id', true ); ?>
						<article class="fms-card">
							<?php if ( $vid ) {
								fms_youtube_facade( $vid, get_the_title() );
							} elseif ( has_post_thumbnail() ) { ?>
								<div class="fms-card__media">
									<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
								</div>
							<?php } ?>
							<div class="fms-card__body">
								<h3 class="fms-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
								<p class="fms-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
							</div>
						</article>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<p>New episodes are on the way. In the meantime, the full archive lives on
					<a href="https://www.youtube.com/@thefadedmainstreet">the Faded Main Street YouTube channel</a>.</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="fms-section">
		<div class="fms-wrap">
			<span class="fms-eyebrow">Recurring themes</span>
			<h2 class="fms-section-title">What we keep coming back to</h2>
			<div class="fms-themes-list">
				<div class="fms-theme">
					<h3><span class="fms-theme-no">i.</span>Ghost signs</h3>
					<p>Hand-painted brick advertisements, faded to a whisper — the last billboards of businesses gone eighty years.</p>
				</div>
				<div class="fms-theme">
					<h3><span class="fms-theme-no">ii.</span>Lost buildings</h3>
					<p>Opera houses, depots, and department stores that anchored a town — and what stands (or doesn't) in their place.</p>
				</div>
				<div class="fms-theme">
					<h3><span class="fms-theme-no">iii.</span>Dead products</h3>
					<p>The brands in every pantry and toolbox of their day, and how a household name becomes a trivia answer.</p>
				</div>
				<div class="fms-theme">
					<h3><span class="fms-theme-no">iv.</span>Wall dogs</h3>
					<p>The itinerant painters who lettered America's walls by hand — the craft behind every ghost sign we film.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="fms-section fms-follow">
		<div class="fms-wrap fms-wrap--narrow">
			<span class="fms-eyebrow">Follow along</span>
			<h2 class="fms-section-title">Don't let it fade without you</h2>
			<p>New documentaries on YouTube, photo stories everywhere else.</p>
			<ul class="fms-follow-links">
				<li><a href="https://www.youtube.com/@thefadedmainstreet">YouTube</a></li>
				<?php /* Uncomment as each profile goes live — keep in sync with the
				         sameAs placeholders in functions.php.
				<li><a href="https://www.pinterest.com/…">Pinterest</a></li>
				<li><a href="https://medium.com/@…">Medium</a></li>
				<li><a href="https://x.com/…">X</a></li>
				<li><a href="https://www.facebook.com/…">Facebook</a></li>
				<li><a href="https://www.instagram.com/…">Instagram</a></li>
				<li><a href="https://www.tumblr.com/…">Tumblr</a></li>
				*/ ?>
			</ul>
		</div>
	</section>

	<div class="fms-footer">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Faded Main Street &middot;
			<a href="https://www.youtube.com/@thefadedmainstreet">YouTube</a> &middot;
			The stories of vanished America.</p>
	</div>

</main>

<?php get_footer(); ?>
