<?php
namespace Sleek\Menu;

#######################
# Remove container & ID
add_filter('wp_nav_menu_args', function ($args) {
	$args['container'] = false;
	$args['items_wrap'] = '<ul class="%2$s %1$s">%3$s</ul>';

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
	# If there are no categories, don't display anything
	if (strpos($output, 'cat-item-none') !== false) {
		return false;
	}

	# Remove title attributes (which can be insanely long)
	# https://www.isitwp.com/remove-title-attribute-from-wp_list_categories/
	# TODO: use_desc_for_title https://developer.wordpress.org/reference/functions/wp_list_categories/
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
			if (is_category() or is_tag() or is_date()) {
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

#############################################################################
# Remove active class from blog when viewing other archives or search results
# https://stackoverflow.com/questions/3269878/wordpress-custom-post-type-hierarchy-and-menu-highlighting-current-page-parent/3270171#3270171
# https://core.trac.wordpress.org/ticket/13543
add_filter('nav_menu_css_class', function ($classes, $item) {
	if ((int) $item->object_id === (int) get_option('page_for_posts') and ((get_post_type() !== 'post') or is_search())) {
		foreach ($classes as $k => $v) {
			if ($v === 'active-parent') {
				unset($classes[$k]);
			}
		}
	}

	return $classes;
}, 10, 2);

#############################################################
# Add active class to post type archive when viewing singular
add_action('wp', function () {
	# Grab all menus
	$allMenus = get_terms(['taxonomy' => 'nav_menu', 'hide_empty' => false]);
	$activeAncestors = [];
	$activeParents = [];

	# And all menu items in each menu
	foreach ($allMenus as $menu) {
		$allItems = wp_get_nav_menu_items($menu);

		foreach ($allItems as $item) {
			# If this menu item posts to a post type archive and we're currently viewing said post-type
			if ($item->type === 'post_type_archive' and is_singular($item->object)) {
				# Store its ID for later
				$activeParents[] = (int) $item->ID;

				# If this menu item has a parent, store its ID too
				if ($item->menu_item_parent) {
					$activeAncestors[] = (int) $item->menu_item_parent;
				}
			}
		}
	}

	# Now add an active class to all stored IDs
	add_filter('nav_menu_css_class', function ($classes, $item) use ($activeAncestors, $activeParents) {
		if (in_array($item->ID, $activeAncestors)) {
			$classes[] = 'active-ancestor';
		}
		if (in_array($item->ID, $activeParents)) {
			$classes[] = 'active-parent';
		}

		return $classes;
	}, 10, 2);


	// Alternative version: Set current property of menu items before output
	// NOTE: Setting current_parent etc here doesn't work, it doesn't add any classes in the end
	/* add_filter('wp_nav_menu_objects', function ($items, $args) {
		$ancestor = null;

		foreach ($items as $item) {
			if ($item->type === 'post_type_archive' and is_singular($item->object)) {
				$item->current_parent = $item->current_item_parent = true;

				if ($item->menu_item_parent) {
					$ancestor = (int) $item->menu_item_parent;
				}
			}
		}

		if ($ancestor) {
			foreach ($items as $item) {
				if ($item->ID === $ancestor) {
					\Sleek\Utils\log($item);
					$item->current_ancestor = $item->current_item_ancestor = true;
				}
			}
		}

		return $items;
	}, 10, 2); */
}, 99);
