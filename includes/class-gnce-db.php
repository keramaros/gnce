<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNCE_DB
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gnce_iccids';
    }

    public function create_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            iccid VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT '',
            email VARCHAR(255) DEFAULT '',
            quotaMB BIGINT(20) DEFAULT 0,
            quotaSMS BIGINT(20) DEFAULT 0,
            thresholdMB BIGINT(20) DEFAULT 250,
            thresholdSMS BIGINT(20) DEFAULT 50,
            lastQuotaUpdated DATETIME DEFAULT NULL,
            lastNotificationSent DATETIME DEFAULT NULL,
            notifyBySMS TINYINT(1) DEFAULT 1,
            notifyByEmail TINYINT(1) DEFAULT 1,
            error VARCHAR(50) DEFAULT '',
            created DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY iccid (iccid)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function get_iccids($args = [])
    {
        global $wpdb;

        $defaults = [
            'orderby' => 'id',
            'order' => 'ASC',
            'number' => 20,
            'offset' => 0,
            'search' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $query = "SELECT * FROM $this->table_name";
        $where = [];
        $prepare_args = [];

        if (!empty($args['search'])) {
            $where[] = "(iccid LIKE %s OR name LIKE %s OR email LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            array_push($prepare_args, $search, $search, $search);
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        // Validate orderby to avoid SQL injection even if it's already using esc_sql
        $allowed_orderby = ['id', 'iccid', 'name', 'email', 'quotaMB', 'quotaSMS', 'lastQuotaUpdated', 'created'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'id';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $query .= " ORDER BY " . $orderby . " " . $order;
        if ($args['number'] > 0) {
            $query .= " LIMIT %d OFFSET %d";
            array_push($prepare_args, $args['number'], $args['offset']);
        }

        $sql = $wpdb->prepare($query, $prepare_args);
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function count_iccids($search = '')
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM $this->table_name";
        if (!empty($search)) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            return $wpdb->get_var($wpdb->prepare("$query WHERE (iccid LIKE %s OR name LIKE %s OR email LIKE %s)", $search, $search, $search));
        }
        return $wpdb->get_var($query);
    }

    public function get_iccid($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $id), ARRAY_A);
    }

    public function insert_iccid($data)
    {
        global $wpdb;
        $data['created'] = current_time('mysql');
        $result = $wpdb->insert($this->table_name, $data);
        if ($result) {
            return $wpdb->insert_id;
        }
        return false;
    }

    public function update_iccid($id, $data)
    {
        global $wpdb;
        return $wpdb->update($this->table_name, $data, ['id' => $id]);
    }

    public function delete_iccid($id)
    {
        global $wpdb;
        return $wpdb->delete($this->table_name, ['id' => $id]);
    }

    public function iccid_exists($iccid, $exclude_id = 0)
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM $this->table_name WHERE iccid = %s";
        $params = [$iccid];
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        return (bool)$wpdb->get_var($wpdb->prepare($query, $params));
    }

    public function drop_table()
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS $this->table_name");
    }

    public function get_table_name()
    {
        return $this->table_name;
    }
}
