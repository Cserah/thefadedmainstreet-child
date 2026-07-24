<?php
/**
 * Template Name: About — Faded Main Street
 * Template Post Type: page
 *
 * Full Broadsheet-style page: same top bar, masthead, bands, and footer as
 * the homepage. Auto-applied to the page with slug "about" (also auto-created
 * by functions.php if missing). Blocksy's header/footer are hidden on this
 * template via style.css.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$fms_assets = get_stylesheet_directory_uri() . '/assets';
?>

<main id="main" class="fms-about-page">

	<?php fms_topbar(); ?>

	<header class="fms-article-hero">
		<span class="fms-eyebrow">About the Channel</span>
		<h1>Every town had a main street.<br>Most of them faded.</h1>
	</header>

	<section class="fms-band fms-about" style="border-bottom:1px solid var(--fms-rule);">
		<div class="fms-wrap fms-wrap--narrow">
			<img class="fms-monogram"
				src="<?php echo esc_url( $fms_assets . '/profile-icon.jpg' ); ?>"
				alt="Faded Main Street monogram — a cream script F painted on brick"
				width="84" height="84" decoding="async" />
			<p>Faded Main Street is a documentary channel about the America that's still visible if you know where to look: hand-painted advertisements bleeding through brick, department stores that anchored a downtown for a century, products every household owned and no one remembers buying last.</p>
			<p>Each episode digs into one place, one sign, or one vanished name &mdash; who built it, why it mattered, and what's left. The articles on this site accompany each video with the photographs, sources, and details that don't fit on screen.</p>
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<?php if ( get_the_content() ) : ?>
					<div style="text-align:left;">
						<?php the_content(); ?>
					</div>
				<?php endif; ?>
			<?php endwhile; endif; ?>
		</div>
	</section>

	<section class="fms-band fms-themes">
		<div class="fms-wrap">
			<span class="fms-eyebrow">What We Cover</span>
			<h3>The beats we keep coming back to</h3>
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

	<section class="fms-subscribe">
		<span class="fms-subscribe__script">Come along for the ride</span>
		<h3>Subscribe on YouTube</h3>
		<p>New documentaries most weeks. Join the crew tracking down what's left of Main Street.</p>
		<a class="fms-btn" href="<?php echo esc_url( FMS_CHANNEL_URL ); ?>">&#9654;&nbsp; Subscribe on YouTube</a>
	</section>

	<?php fms_broadsheet_footer(); ?>

</main>

<?php get_footer(); ?>
