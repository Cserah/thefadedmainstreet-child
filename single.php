<?php
/**
 * Single article — long-form story with the episode video up top.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>

<main id="main" class="fms-single">
<?php while ( have_posts() ) : the_post();
	$vid = get_post_meta( get_the_ID(), 'fms_youtube_id', true ); ?>

	<header class="fms-article-hero">
		<span class="fms-eyebrow">Faded Main Street</span>
		<h1><?php the_title(); ?></h1>
		<p class="fms-article-meta">
			<?php echo esc_html( get_the_date() ); ?>
			<?php $cats = get_the_category_list( ', ' );
			if ( $cats ) { echo ' &middot; ' . wp_kses_post( $cats ); } ?>
		</p>
	</header>

	<?php if ( $vid ) : ?>
		<div class="fms-article-video">
			<?php fms_youtube_facade( $vid, get_the_title(), true ); ?>
		</div>
	<?php endif; ?>

	<article <?php post_class( 'fms-section fms-article-body' ); ?>>
		<div class="fms-wrap fms-wrap--narrow">
			<?php the_content(); ?>
		</div>
	</article>

	<section class="fms-section fms-follow">
		<div class="fms-wrap fms-wrap--narrow" style="text-align:center;">
			<span class="fms-eyebrow">Keep exploring</span>
			<h2 class="fms-section-title">More stories of vanished America</h2>
			<p><a class="fms-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">All episodes</a>
			&nbsp; <a class="fms-btn fms-btn--solid" href="https://www.youtube.com/@thefadedmainstreet">Subscribe on YouTube</a></p>
		</div>
	</section>

<?php endwhile; ?>
</main>

<?php get_footer(); ?>
