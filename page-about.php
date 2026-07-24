<?php
/**
 * Template Name: About — Faded Main Street
 * Template Post Type: page
 *
 * Also auto-applies to the page with slug "about" via WP template hierarchy.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$fms_assets = get_stylesheet_directory_uri() . '/assets';
?>

<main id="main" class="fms-about-page">
<?php while ( have_posts() ) : the_post(); ?>

	<header class="fms-article-hero">
		<span class="fms-eyebrow">Faded Main Street</span>
		<h1><?php the_title(); ?></h1>
	</header>

	<section class="fms-section fms-about">
		<div class="fms-wrap fms-wrap--narrow">
			<img class="fms-monogram"
				src="<?php echo esc_url( $fms_assets . '/profile-icon.jpg' ); ?>"
				alt="Faded Main Street monogram — a cream script F painted on brick"
				width="84" height="84" decoding="async" />
			<h2 class="fms-section-title">Every town had a main street. Most of them faded.</h2>
			<p>Faded Main Street is a documentary channel about the America that's still visible if you know where to look: hand-painted advertisements bleeding through brick, department stores that anchored a downtown for a century, products every household owned and no one remembers buying last.</p>
			<p>Each episode digs into one place, one sign, or one vanished name — who built it, why it mattered, and what's left. The articles on this site accompany each video with the photographs, sources, and details that don't fit on screen.</p>
			<?php if ( get_the_content() ) : ?>
				<div class="fms-article-body" style="padding-top:0;">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>
			<p style="margin-top:2rem;">
				<a class="fms-btn fms-btn--solid" href="https://www.youtube.com/@thefadedmainstreet">Watch on YouTube</a>
			</p>
		</div>
	</section>

<?php endwhile; ?>
</main>

<?php get_footer(); ?>
