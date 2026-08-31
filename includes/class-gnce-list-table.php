<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class GNCE_List_Table extends WP_List_Table
{
    private $db;

    public function __construct()
    {
        parent::__construct([
            'singular' => __('ICCID', 'gnce-1nce-products'),
            'plural' => __('ICCIDs', 'gnce-1nce-products'),
            'ajax' => false
        ]);
        $this->db = new GNCE_DB();
    }

    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'iccid' => __('ICCID', 'gnce-1nce-products'),
            'name' => __('Name', 'gnce-1nce-products'),
            'email' => __('Email', 'gnce-1nce-products'),
            'quotaMB' => __('Quota (MB)', 'gnce-1nce-products'),
            'quotaSMS' => __('Quota (SMS)', 'gnce-1nce-products'),
            'lastQuotaUpdated' => __('Last Updated', 'gnce-1nce-products'),
            'error' => __('Status/Error', 'gnce-1nce-products'),
        ];
    }

    public function get_bulk_actions()
    {
        return [
            'bulk-delete' => __('Delete', 'gnce-1nce-products'),
            'bulk-sync' => __('Sync Now', 'gnce-1nce-products'),
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'iccid' => ['iccid', false],
            'name' => ['name', false],
            'email' => ['email', false],
            'quotaMB' => ['quotaMB', false],
            'quotaSMS' => ['quotaSMS', false],
            'lastQuotaUpdated' => ['lastQuotaUpdated', false],
        ];
    }


    public function column_name($item)
    {
        return esc_html($item['name']);
    }

    public function column_email($item)
    {
        return esc_html($item['email']);
    }

    public function column_quotaMB($item)
    {
        return esc_html($item['quotaMB']);
    }

    public function column_quotaSMS($item)
    {
        return esc_html($item['quotaSMS']);
    }

    public function column_lastQuotaUpdated($item)
    {
        if (empty($item['lastQuotaUpdated'])) {
            return __('Never', 'gnce-1nce-products');
        }
        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['lastQuotaUpdated']);
    }

    public function column_error($item)
    {
        return esc_html($item['error']);
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="iccid_id[]" value="%s" />',
            $item['id']
        );
    }

    public function column_iccid($item)
    {
        $id = $item['id'];
        $actions = [
            'edit' => sprintf('<a href="?page=%s&action=%s&id=%s">%s</a>', 'gnce-add-iccid', 'edit', $id, __('Edit', 'gnce-1nce-products')),
            'delete' => sprintf(
                '<a href="?page=%s&action=%s&id=%s&_wpnonce=%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_attr($_REQUEST['page'] ?? 'gnce-iccids'),
                'delete',
                $id,
                wp_create_nonce('gnce_delete_iccid_' . $id),
                __('Are you sure you want to delete this ICCID?', 'gnce-1nce-products'),
                __('Delete', 'gnce-1nce-products')
            ),
            'sync' => sprintf(
                '<a href="?page=%s&action=%s&id=%s&_wpnonce=%s">%s</a>',
                esc_attr($_REQUEST['page'] ?? 'gnce-iccids'),
                'sync',
                $id,
                wp_create_nonce('gnce_sync_iccid_' . $id),
                __('Sync Now', 'gnce-1nce-products')
            ),
        ];

        return sprintf('%1$s %2$s', sprintf('<a href="?page=%s&action=%s&id=%s">%s</a>', 'gnce-add-iccid', 'edit', $id, esc_html($item['iccid'])), $this->row_actions($actions));
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'iccid'];

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC';

        $this->items = $this->db->get_iccids([
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => $orderby,
            'order' => $order,
            'search' => $search
        ]);

        $total_items = $this->db->count_iccids($search);

        $this->set_pagination_args([
            'total_items' => (int)$total_items,
            'per_page' => $per_page
        ]);
    }
}
