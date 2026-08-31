<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class GNCE_Product_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Product', 'gnce-1nce-products'),
            'plural' => __('Products', 'gnce-1nce-products'),
            'ajax' => false
        ]);
    }

    public function get_columns()
    {
        return [
            'name' => __('Name', 'gnce-1nce-products'),
            'sku' => __('SKU', 'gnce-1nce-products'),
            'price' => __('Price', 'gnce-1nce-products'),
        ];
    }

    public function column_name($item)
    {
        $product = wc_get_product($item->get_id());
        $edit_url = get_edit_post_link($product->get_id());
        $view_url = get_permalink($product->get_id());

        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'gnce-1nce-products')),
            'view' => sprintf('<a href="%s" target="_blank">%s</a>', esc_url($view_url), __('View', 'gnce-1nce-products')),
        ];

        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong> %s',
            esc_url($edit_url),
            esc_html($product->get_name()),
            $this->row_actions($actions)
        );
    }

    public function column_sku($item)
    {
        return esc_html($item->get_sku());
    }

    public function column_price($item)
    {
        return $item->get_price_html();
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $args = [
            'limit' => $per_page,
            'page' => $current_page,
            'paginate' => true,
            'meta_key' => '_requires_1nce_iccid',
            'meta_value' => 'yes',
            'meta_compare' => '=',
        ];

        // Handle search
        if (!empty($_REQUEST['s'])) {
            $args['s'] = sanitize_text_field($_REQUEST['s']);
        }

        // Handle sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'name';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
        $args['orderby'] = $orderby;
        $args['order'] = $order;

        $results = wc_get_products($args);

        $this->items = $results->products;

        $this->set_pagination_args([
            'total_items' => $results->total,
            'per_page' => $per_page,
            'total_pages' => $results->max_num_pages,
        ]);
    }

    protected function get_sortable_columns()
    {
        return [
            'name' => ['name', false],
            'sku' => ['sku', false],
            'price' => ['price', false],
        ];
    }
}
