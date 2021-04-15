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

#########################################################################
# Remove active class from blog when viewing other CPTs or search results
# https://stackoverflow.com/questions/3269878/wordpress-custom-post-type-hierarchy-and-menu-highlighting-current-page-parent/3270171#3270171
# https://core.trac.wordpress.org/ticket/13543
add_filter('nav_menu_css_class', function ($classes, $item) {
	if (
		(int) $item->object_id === (int) get_option('page_for_posts') and
		(
			is_search() or
			(is_singular() and !is_singular('post')) or
			is_tax() or
			(is_post_type_archive() and !is_home())
		)
	) {
		foreach ($classes as $k => $v) {
			if ($v === 'active-parent') {
				unset($classes[$k]);
			}
		}
	}

	return $classes;
}, 10, 2);

########################################
# Remove active class everywhere on SERP
add_filter('nav_menu_css_class', function ($classes) {
	if (is_search()) {
		foreach ($classes as $k => $v) {
			if ($v === 'active' or $v === 'active-parent' or $v === 'active-ancestor') {
				unset($classes[$k]);
			}
		}
	}

	return $classes;
}, 10);

############################################################
# Fix active classes relating to CPT archives and taxonomies
function get_nav_menu_item_by_id ($id, $items) {
	foreach ($items as $item) {
		if ($id === $item->ID) {
			return $item;
		}
	}

	return null;
}

add_action('wp', function () {
	global $wp_query;

	# Grab all menus
	$allMenus = get_terms(['taxonomy' => 'nav_menu', 'hide_empty' => false]);
	$activeAncestors = [];
	$activeParents = [];

	# And all menu items in each menu
	foreach ($allMenus as $menu) {
		$allItems = wp_get_nav_menu_items($menu);

		foreach ($allItems as $item) {
			if (
				# If this menu item points to a post type archive and we're currently viewing said post-type
				(
					($item->type === 'post_type_archive' and is_singular($item->object)) or
					((int) $item->object_id === (int) get_option('page_for_posts') and is_singular('post'))
				) or
				# If this menu item points to the blog and we're on a category, tag or date
				(
					get_option('page_for_posts') and $item->object_id === get_option('page_for_posts') and
					(is_category() or is_tag() or is_date())
				) or
				# If this menu item points to a post type archive and we're viewing one of its taxonomies
				(
					$item->type === 'post_type_archive' and is_tax() and
					is_object_in_taxonomy($item->object, $wp_query->get_queried_object()->taxonomy)
				)
			) {
				# Store its ID for later
				$activeParents[] = (int) $item->ID;

				# If this menu item has a parent, store its ID too
				if ($item->menu_item_parent) {
					$parent = $item;

					# And all its potential parents
					while ($parent = get_nav_menu_item_by_id((int) $parent->menu_item_parent, $allItems)) {
						$activeAncestors[] = (int) $parent->ID;
					}
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

		return array_unique($classes);
	}, 10, 2);
}, 99);
