<?php
namespace Sleek\Menu;

#######################
# Remove container & ID
add_filter('wp_nav_menu_args', function ($args) {
	$args['container'] = false;
	$args['items_wrap'] = '<ul class="%2$s">%3$s</ul>';

	return $args;
});

################
# Remove item ID
add_filter('nav_menu_item_id', '__return_null');

##################
# Clean up classes
add_filter('nav_menu_css_class', function ($classes, $item) {
	$classMap = [
		'current-menu-ancestor' => 'active-ancestor',
		'current_page_ancestor' => 'active-ancestor',
		'current-menu-parent' => 'active-parent',
		'current_page_parent' => 'active-parent',
		'current-menu-item' => 'active',
		'current_page_item' => 'active',
		'menu-item-has-children' => 'dropdown'
	];
	$classRemove = [
		'/menu\-item/',
		'/menu\-item-.*/'
	];
	$newClasses = [];
#	$newClasses[] = sanitize_title($item->title);

	foreach ($classes as $class) {
		if (isset($classMap[$class])) {
			$newClasses[] = $classMap[$class];
		}
		else {
			$remove = false;

			foreach ($classRemove as $regex) {
				if (preg_match($regex, $class)) {
					$remove = true;
				}
			}

			if (!$remove) {
				$newClasses[] = $class;
			}
		}
	}

	return $newClasses;
}, 10, 2);

#############################
# Clean up wp_list_categories
add_action('wp_list_categories', function ($output) {
	# Remove title attributes (which can be insanely long)
	# https://www.isitwp.com/remove-title-attribute-from-wp_list_categories/
	$output = preg_replace('/ title="(.*?)"/s', '', $output);

	# Replace current-cat classes
	$output = str_replace(['current-cat-ancestor', 'current-cat-parent', 'current-cat'], ['active-ancestor', 'active-parent', 'active'], $output);

	# If there's no current cat - add the class to the "all" link
	if (strpos($output, 'active') === false) {
		$output = str_replace('cat-item-all', 'cat-item-all ' . 'active', $output);
	}

	# Remove cat-item* classes and do more cleanup
	$output = preg_replace('/cat-item-[0-9]+/', '', $output);
	$output = str_replace('cat-item-all', '', $output);
	$output = str_replace('cat-item', '', $output);
	$output = str_replace(" class=''", '', $output);
	$output = str_replace(' class=" "', '', $output);
	$output = str_replace("class=' active'", 'class="active"', $output);
	$output = str_replace("<ul class='children'", '<ul', $output);
	$output = str_replace('class="  ', 'class="', $output);

	# If there are no categories, don't display anything
	if (strpos($output, 'cat-item-none') !== false) {
		$output = false;
	}

	return $output;
});

####################################
# Give the correct post type archive
# an active class when viewing its taxonomies
add_filter('nav_menu_css_class', function ($classes, $item) {
	global $wp_query;

	# Only do this on archive pages
	if (is_archive()) {
		# This is the link to the blog archive
		if (get_option('page_for_posts') and $item->object_id === get_option('page_for_posts')) {
			# If we're on a blog archive - give the blog link the active class
			if (is_category() or is_tag() or is_day() or is_month() or is_year()) {
				$classes[] = 'active-parent';
			}
		}
		# This is a link to a custom post type archive
		elseif ($item->type === 'post_type_archive') {
			# If we're on a taxonomy and this post type has that taxonomy - make it look active
			if (is_tax()) {
				$term = $wp_query->get_queried_object();

				if (is_object_in_taxonomy($item->object, $term->taxonomy)) {
					$classes[] = 'active-parent';
				}
			}
		}
	}

	return array_unique($classes);
}, 10, 2);

###############################
# Remove active class from blog
# when viewing other archives
# https://stackoverflow.com/questions/3269878/wordpress-custom-post-type-hierarchy-and-menu-highlighting-current-page-parent/3270171#3270171
# https://core.trac.wordpress.org/ticket/13543
add_filter('nav_menu_css_class', function ($classes, $item) {
	if (get_post_type() !== 'post' and $item->object_id === get_option('page_for_posts')) {
		foreach ($classes as $k => $v) {
			if ($v === 'active-parent') {
				unset($classes[$k]);
			}
		}
	}

	return $classes;
}, 10, 2);

#######################################
# Add active class to post type archive
# when viewing singular
add_filter('nav_menu_css_class', function ($classes, $item) {
	if ($item->type === 'post_type_archive' and is_singular($item->object)) {
		$classes[] = 'active-parent';
	}

	return $classes;
}, 10, 2);
