<?php

namespace Helper;

use Helper\Columns;

/**
 * PostType
 *
 * Create WordPress custom post types easily
 *
 * @link http://github.com/jjgrainger/PostTypes/
 * @author  jjgrainger
 * @link    http://jjgrainger.co.uk
 * @version 2.0
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 */
class PostType
{
    /**
     * The names passed to the PostType
     * @var array
     */
    public $names;

    /**
     * The name for the PostType
     * @var array
     */
    public $name;

    /**
     * The singular for the PostType
     * @var array
     */
    public $singular;

    /**
     * The plural name for the PostType
     * @var array
     */
    public $plural;

    /**
     * The slug for the PostType
     * @var array
     */
    public $slug;

    /**
     * Options for the PostType
     * @var array
     */
    public $options;

    /**
     * Labels for the PostType
     * @var array
     */
    public $labels;

    /**
     * Taxonomies for the PostType
     * @var array
     */
    public $taxonomies = [];

	/**
	 * Meta Boxes for the PostType
	 * @var array
	 */
	public $metaboxes = [];

    /**
     * Filters for the PostType
     * @var mixed
     */
    public $filters;

    /**
     * The menu icon for the PostType
     * @var string
     */
    public $icon;

    /**
     * The column manager for the PostType
     * @var mixed
     */
    public $columns;

    /**
     * Create a PostType
     * @param mixed $names   A string for the name, or an array of names
     * @param array $options An array of options for the PostType
     */
    public function __construct($names, $options = [], $labels = [])
    {
        // assign names to the PostType
        $this->names($names);

        // assign custom options to the PostType
        $this->options($options);

        // assign labels to the PostType
        $this->labels($labels);
    }

    /**
     * Set the names for the PostType
     * @param  mixed $names A string for the name, or an array of names
     * @return $this
     */
    public function names($names)
    {
        // only the post type name is passed
        if (is_string($names)) {
            $names = ['name' => $names];
        }

        // set the names array
        $this->names = $names;

        // create names for the PostType
        $this->createNames();

        return $this;
    }

    /**
     * Set the options for the PostType
     * @param  array $options An array of options for the PostType
     * @return $this
     */
    public function options(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Set the labels for the PostType
     * @param  array $labels An array of labels for the PostType
     * @return $this
     */
    public function labels(array $labels)
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * Add a Taxonomy to the PostType
     * @param  string $taxonomy The Taxonomy name to add
     * @return $this
     */
    public function taxonomy($taxonomy)
    {
        $this->taxonomies[] = $taxonomy;

        return $this;
    }

	/**
	 * Add a Meta Box to the PostType
	 *
	 * @param $title
	 * @param array $fields
	 * @param string $context
	 * @param string $priority
	 *
	 * @return PostType
	 */
	public function metabox($title, $fields = array(), $context = 'normal', $priority = 'default' )
	{
		$this->metaboxes[] = array('title' => $title, 'fields' => $fields, 'context' => $context, 'priority' => $priority);
		return $this;
	}

    /**
     * Add filters to the PostType
     * @param  array $filters An array of Taxonomy filters
     * @return $this
     */
    public function filters(array $filters)
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Set the menu icon for the PostType
     * @param  string $icon A dashicon class for the menu icon
     * @return $this
     */
    public function icon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Flush rewrite rules
     * @link https://codex.wordpress.org/Function_Reference/flush_rewrite_rules
     * @param  boolean $hard
     * @return void
     */
    public function flush($hard = true)
    {
        flush_rewrite_rules($hard);
    }

    /**
     * Get the Column Manager for the PostType
     * @return PostTypes\Columns
     */
    public function columns()
    {
        if (!isset($this->columns)) {
            $this->columns = new Columns;
        }

        return $this->columns;
    }

    /**
     * Register the PostType to WordPress
     * @return void
     */
    public function register()
    {
        // register the PostType
        add_action('init', [&$this, 'registerPostType']);

        // register Taxonomies to the PostType
        add_action('init', [&$this, 'registerTaxonomies']);

	    // register MetaBoxes to the PostType
	    add_action('init', [&$this, 'registerMetaBoxes']);

        // modify filters on the admin edit screen
        add_action('restrict_manage_posts', [&$this, 'modifyFilters']);

        if (isset($this->columns)) {
            // modify the admin edit columns.`
            add_filter("manage_{$this->name}_posts_columns", [&$this, 'modifyColumns'], 10, 1);

            // populate custom columns
            add_filter("manage_{$this->name}_posts_custom_column", [&$this, 'populateColumns'], 10, 2);

            // run filter to make columns sortable.
            add_filter('manage_edit-'.$this->name.'_sortable_columns', [&$this, 'setSortableColumns']);

            // run action that sorts columns on request.
            add_action('pre_get_posts', [&$this, 'sortSortableColumns']);
        }

	    // Listen for the save post hook
	    $this->saveCustomFields();

    }

    /**
     * Register the PostType
     * @return void
     */
    public function registerPostType()
    {
        // create options for the PostType
        $options = $this->createOptions();

        // check that the post type doesn't already exist
        if (!post_type_exists($this->name)) {
            // register the post type
            register_post_type($this->name, $options);
        }
    }

    /**
     * Create the required names for the PostType
     * @return void
     */
    public function createNames()
    {
        // names required for the PostType
        $required = [
            'name',
            'singular',
            'plural',
            'slug',
        ];

        foreach ($required as $key) {
            // if the name is set, assign it
            if (isset($this->names[$key])) {
                $this->$key = $this->names[$key];
                continue;
            }

            // if the key is not set and is singular or plural
            if (in_array($key, ['singular', 'plural'])) {
                // create a human friendly name
                $name = ucwords(strtolower(str_replace(['-', '_'], ' ', $this->names['name'])));
            }

            if ($key === 'slug') {
                // create a slug friendly name
                $name = strtolower(str_replace([' ', '_'], '-', $this->names['name']));
            }

            // if is plural or slug, append an 's'
            if (in_array($key, ['plural', 'slug'])) {
                $name .= 's';
            }

            // asign the name to the PostType property
            $this->$key = $name;
        }
    }

    /**
     * Create options for PostType
     * @return array Options to pass to register_post_type
     */
    public function createOptions()
    {
        // default options
        $options = [
            'public' => true,
            'rewrite' => [
                'slug' => $this->slug
            ]
        ];

        // replace defaults with the options passed
        $options = array_replace_recursive($options, $this->options);

        // create and set labels
        if (!isset($options['labels'])) {
            $options['labels'] = $this->createLabels();
        }

        // set the menu icon
        if (!isset($options['menu_icon']) && isset($this->icon)) {
            $options['menu_icon'] = $this->icon;
        }

        return $options;
    }

    /**
     * Create the labels for the PostType
     * @return array
     */
    public function createLabels()
    {
        // default labels
        $labels = [
            'name' => $this->plural,
            'singular_name' => $this->singular,
            'menu_name' => $this->plural,
            'all_items' => $this->plural,
            'add_new' => "Add New",
            'add_new_item' => "Add New {$this->singular}",
            'edit_item' => "Edit {$this->singular}",
            'new_item' => "New {$this->singular}",
            'view_item' => "View {$this->singular}",
            'search_items' => "Search {$this->plural}",
            'not_found' => "No {$this->plural} found",
            'not_found_in_trash' => "No {$this->plural} found in Trash",
            'parent_item_colon' => "Parent {$this->singular}:",
        ];

        return array_replace_recursive($labels, $this->labels);
    }

    /**
     * Register Taxonomies to the PostType
     * @return void
     */
    public function registerTaxonomies()
    {
        if (!empty($this->taxonomies)) {
            foreach ($this->taxonomies as $taxonomy) {
                register_taxonomy_for_object_type($taxonomy, $this->name);
            }
        }
    }

	/**
	 * Register Meta Boxes to the PostType
	 * @return void
	 */
	public function registerMetaBoxes()
	{
		if (!empty($this->metaboxes)) {
			foreach ($this->metaboxes as $metabox) {
				$this->registerMetaBoxSingle($metabox['title'], $metabox['fields'], $metabox['context'], $metabox['priority']);
			}
		}
	}

	/* Attaches single meta box to the post type */
	/**
	 * @param $title
	 * @param array $fields
	 * @param string $context
	 * @param string $priority
	 */
	public function registerMetaBoxSingle( $title, $fields = array(), $context = 'normal', $priority = 'default' )
	{
		if( ! empty( $title ) )
		{
			// We need to know the Post Type name again
			$post_type_name = $this->name;

			// Meta variables
			$box_id         = strtolower( str_replace( ' ', '_', $title ) );
			$box_title      = ucwords( str_replace( '_', ' ', $title ) );
			$box_context    = $context;
			$box_priority   = $priority;

			// Make the fields global
			global $custom_fields;
			$custom_fields[$title] = $fields;

			add_action( 'admin_init',
				function() use( $box_id, $box_title, $post_type_name, $box_context, $box_priority, $fields )
				{
					add_meta_box(
						$box_id,
						$box_title,
						function( $post, $data )
						{
							global $post;

							// Nonce field for some validation
							wp_nonce_field( plugin_basename( __FILE__ ), 'custom_post_type' );

							// Get all inputs from $data
							$custom_fields = $data['args'][0];

							// Get the saved values
							$meta = get_post_custom( $post->ID );

							// Check the array and loop through it
							if( ! empty( $custom_fields ) )
							{
								/* Loop through $custom_fields */
								foreach( $custom_fields as $label => $type )
								{
									$field_id_name  = strtolower( str_replace( ' ', '_', $data['id'] ) ) . '_' . strtolower( str_replace( ' ', '_', $label ) );

									echo '<label for="' . $field_id_name . '">' . $label . '</label><input type="text" name="custom_meta[' . $field_id_name . ']" id="' . $field_id_name . '" value="' . (isset($meta[$field_id_name]) ? $meta[$field_id_name][0]:"") . '" />';
								}
							}

						},
						$post_type_name,
						$box_context,
						$box_priority,
						array( $fields )
					);
				}
			);
		}

	}

	/* Listens for when the post type being saved */
	public function saveCustomFields()
	{
		// Need the post type name again
		$post_type_name = $this->name;

		add_action( 'save_post_' . $post_type_name,
			function() use( $post_type_name )
			{
				// Deny the WordPress autosave function
				if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

				if ( ! wp_verify_nonce( $_REQUEST['custom_post_type'], plugin_basename(__FILE__) ) ) return;

				global $post;

				if( isset( $_POST ) && isset( $post->ID ) && get_post_type( $post->ID ) == $post_type_name )
				{
					global $custom_fields;

					// Loop through each meta box
					foreach( $custom_fields as $title => $fields )
					{
						// Loop through all fields
						foreach( $fields as $label => $type )
						{
							$field_id_name  = strtolower( str_replace( ' ', '_', $title ) ) . '_' . strtolower( str_replace( ' ', '_', $label ) );

							update_post_meta( $post->ID, $field_id_name, $_POST['custom_meta'][$field_id_name] );
						}

					}
				}
			}
		);
	}

    /**
     * Modify and display filters on the admin edit screen
     * @param  string $posttype The current screen post type
     * @return void
     */
    public function modifyFilters($posttype)
    {
        // first check we are working with the this PostType
        if ($posttype === $this->name) {
            // calculate what filters to add
            $filters = $this->getFilters();

            foreach ($filters as $taxonomy) {
                // if the taxonomy doesn't exist, ignore it
                if (!taxonomy_exists($taxonomy)) {
                    continue;
                }

                // get the taxonomy object
                $tax = get_taxonomy($taxonomy);

                // get the terms for the taxonomy
                $terms = get_terms([
                    'taxonomy' => $taxonomy,
                    'orderby' => 'name',
                    'hide_empty' => false,
                ]);

                // if there are no terms in the taxonomy, ignore it
                if (empty($terms)) {
                    continue;
                }

                // start the html for the filter dropdown
                $selected = null;

                if (isset($_GET[$taxonomy])) {
                    $selected = sanitize_title($_GET[$taxonomy]);
                }

                $dropdown_args = [
                    'option_none_value' => '',
                    'hide_empty'        => 0,
                    'hide_if_empty'     => false,
                    'show_count'        => true,
                    'taxonomy'          => $tax->name,
                    'name'              => $taxonomy,
                    'orderby'           => 'name',
                    'hierarchical'      => true,
                    'show_option_none'  => "Show all {$tax->label}",
                    'value_field'       => 'slug',
                    'selected'          => $selected
                ];

                wp_dropdown_categories($dropdown_args);
            }
        }
    }

    /**
     * Calculate the filters for the PostType
     * @return array
     */
    public function getFilters()
    {
        // default filters are empty
        $filters = [];

        // if custom filters have been set, use them
        if (!is_null($this->filters)) {
            return $this->filters;
        }

        // if no custom filters have been set, and there are
        // Taxonomies assigned to the PostType
        if (is_null($this->filters) && !empty($this->taxonomies)) {
            // create filters for each taxonomy assigned to the PostType
            return $this->taxonomies;
        }

        return $filters;
    }

    /**
     * Modify the columns for the PostType
     * @param  array  $columns  Default WordPress columns
     * @return array            The modified columns
     */
    public function modifyColumns($columns)
    {
        $columns = $this->columns->modifyColumns($columns);

        return $columns;
    }

    /**
     * Populate custom columns for the PostType
     * @param  string $column   The column slug
     * @param  int    $post_id  The post ID
     */
    public function populateColumns($column, $post_id)
    {
        if (isset($this->columns->populate[$column])) {
            call_user_func_array($this->columns()->populate[$column], [$column, $post_id]);
        }
    }

	/**
	 * Make custom columns sortable
	 *
	 * @param array $columns Default WordPress sortable columns
	 *
	 * @return array
	 */
    public function setSortableColumns($columns)
    {
        if (!empty($this->columns()->sortable)) {
            $columns = array_merge($columns, $this->columns()->sortable);
        }

        return $columns;
    }

    /**
     * Set query to sort custom columns
     * @param  WP_Query $query
     */
    public function sortSortableColumns($query)
    {
        // don't modify the query if we're not in the post type admin
        if (!is_admin() || $query->get('post_type') !== $this->name) {
            return;
        }

        $orderby = $query->get('orderby');

        // if the sorting a custom column
        if (array_key_exists($orderby, $this->columns()->sortable)) {
            // get the custom column options
            $meta = $this->columns()->sortable[$orderby];

            // determine type of ordering
            if (is_string($meta)) {
                $meta_key = $meta;
                $meta_value = 'meta_value';
            } else {
                $meta_key = $meta[0];
                $meta_value = 'meta_value_num';
            }

            // set the custom order
            $query->set('meta_key', $meta_key);
            $query->set('orderby', $meta_value);
        }
    }

	public function debug( $text ) {
		$f = fopen( ABSPATH . '/debug.txt', 'a+' );
		fwrite( $f, print_r( $text, true ) . PHP_EOL );
		fclose( $f );
	}

}
