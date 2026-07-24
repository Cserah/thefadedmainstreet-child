<?php
/**
 * Single article — long-form story with the episode video up top.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>

<main id="main" class="fms-single">

<?php fms_topbar(); ?>

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

	<article <?php post_class( 'fms-article-body' ); ?>>
		<div class="fms-wrap fms-wrap--narrow">
			<?php the_content(); ?>
		</div>
	</article>

	<section class="fms-subscribe">
		<span class="fms-subscribe__script">Keep exploring</span>
		<h3>More stories of vanished America</h3>
		<p>New documentaries most weeks. Join the crew tracking down what's left of Main Street.</p>
		<p>
			<a class="fms-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">All stories</a>
			&nbsp; <a class="fms-btn" href="https://www.youtube.com/@thefadedmainstreet">&#9654;&nbsp; Subscribe on YouTube</a>
		</p>
	</section>

<?php endwhile; ?>

	<?php fms_broadsheet_footer(); ?>

</main>

<?php get_footer(); ?>
