<?php
// pagination_injector.php

function inject_pagination($file, $query_var, $table_name, $where_var = '', $order_clause = 'ORDER BY $sort_by $order', $is_complex = false, $complex_count = '') {
    $content = file_get_contents($file);
    if (strpos($content, '$limit = 6;') !== false) {
        echo "Pagination already in $file\n";
        return;
    }

    // 1. Setup Pagination Variables & Count Query
    $where_insert = $where_var ? " WHERE $where_var" : "";
    if ($is_complex && $complex_count) {
        $count_sql = $complex_count;
    } else {
        $count_sql = "\"SELECT COUNT(*) as total FROM $table_name$where_insert\"";
    }

    $setup = <<<PHP
// PAGINATION SETUP
\$limit = 6;
\$page = isset(\$_GET['page']) ? max(1, (int)\$_GET['page']) : 1;
\$offset = (\$page - 1) * \$limit;

// COUNT TOTAL RECORDS
\$count_sql = $count_sql;
\$count_res = \$conn->query(\$count_sql);
\$total_records = \$count_res->fetch_assoc()['total'];
\$total_pages = ceil(\$total_records / \$limit);
PHP;

    // We need to find the main query assignment and insert the setup right before it.
    // Example: $products_sql = "SELECT ...
    $pattern = '/(\$'.preg_quote($query_var, '/').'\s*=\s*".*?)('.preg_quote($order_clause, '/').')(".*?);/s';
    
    $content = preg_replace_callback($pattern, function($matches) use ($setup) {
        return $setup . "\n\n" . $matches[1] . $matches[2] . " LIMIT \$limit OFFSET \$offset" . $matches[3] . ";";
    }, $content, 1, $count);

    if ($count === 0) {
        // Try without order clause if it's dynamic
        $pattern2 = '/(\$'.preg_quote($query_var, '/').'\s*=\s*".*?)(".*?);/s';
        $content = preg_replace_callback($pattern2, function($matches) use ($setup) {
            return $setup . "\n\n" . $matches[1] . " LIMIT \$limit OFFSET \$offset" . $matches[2] . ";";
        }, $content, 1, $count2);
        if ($count2 === 0) {
            echo "FAILED to find query in $file\n";
            return;
        }
    }

    // 2. Inject Pagination UI
    $ui = <<<PHP

            <?php if (\$total_pages > 1): ?>
            <div class="admin-pagination">
                <?php
                \$query_string = \$_GET;
                if (\$page > 1) {
                    \$query_string['page'] = \$page - 1;
                    echo '<a href="?' . http_build_query(\$query_string) . '" class="btn btn-outline">&laquo; PREV</a>';
                }
                for (\$i = 1; \$i <= \$total_pages; \$i++) {
                    \$query_string['page'] = \$i;
                    \$active = (\$i === \$page) ? 'active' : '';
                    echo '<a href="?' . http_build_query(\$query_string) . '" class="btn btn-outline ' . \$active . '">' . \$i . '</a>';
                }
                if (\$page < \$total_pages) {
                    \$query_string['page'] = \$page + 1;
                    echo '<a href="?' . http_build_query(\$query_string) . '" class="btn btn-outline">NEXT &raquo;</a>';
                }
                ?>
            </div>
            <?php endif; ?>
PHP;

    $content = str_replace("</table>", "</table>\n" . $ui, $content);
    
    file_put_contents($file, $content);
    echo "Successfully injected pagination into $file\n";
}

// target files
$dir = __DIR__;
inject_pagination("$dir/marketplace-products.php", "products_sql", "products", "\$where_sql");
inject_pagination("$dir/registered-users.php", "users_sql", "users", "\$where_sql");
inject_pagination("$dir/client-orders.php", "orders_sql", "orders", "\$where_sql");
inject_pagination("$dir/accept-product.php", "pending_sql", "products", "is_marketplace=1 AND is_approved=0");
inject_pagination("$dir/accept-reels.php", "pending_sql", "reels", "is_approved=0");
inject_pagination("$dir/reels.php", "reels_sql", "reels", "\$where_sql");
inject_pagination("$dir/review-qna.php", "qna_sql", "community_qna", "status = '\$st'");
inject_pagination("$dir/review-shoutouts.php", "shoutouts_sql", "community_shoutouts", "status = '\$st'");
inject_pagination("$dir/mag.php", "posts_sql", "magazine_posts", "1=1");
inject_pagination("$dir/verify-seller.php", "sellers_sql", "users", "role='client'");
inject_pagination("$dir/reports.php", "tickets_sql", "support_tickets", "status = '\$st'");
inject_pagination("$dir/admin-users.php", "admins_sql", "users", "role='admin'");
?>
