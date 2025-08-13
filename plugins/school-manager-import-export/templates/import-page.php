<?php
/**
 * Import Page Template
 * 
 * @package    School_Manager_Import_Export
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="import-export-section">
        <h2><?php esc_html_e('Import Students', 'school-manager-import-export'); ?></h2>
        
        <form id="chunked-import-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('import_export_nonce', 'import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="csv_file"><?php esc_html_e('CSV File', 'school-manager-import-export'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">
                            <?php esc_html_e('Upload a CSV file containing student data.', 'school-manager-import-export'); ?>
                            <a href="#" id="generate-test-csv"><?php esc_html_e('Download sample CSV', 'school-manager-import-export'); ?></a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="delimiter"><?php esc_html_e('CSV Delimiter', 'school-manager-import-export'); ?></label>
                    </th>
                    <td>
                        <select name="delimiter" id="delimiter">
                            <option value=",">, (<?php esc_html_e('Comma', 'school-manager-import-export'); ?>)</option>
                            <option value=";">; (<?php esc_html_e('Semicolon', 'school-manager-import-export'); ?>)</option>
                            <option value="\t">\t (<?php esc_html_e('Tab', 'school-manager-import-export'); ?>)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="update_existing">
                            <?php esc_html_e('Update Existing', 'school-manager-import-export'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="update_existing" id="update_existing" value="1">
                            <?php esc_html_e('Update existing students if they already exist', 'school-manager-import-export'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <div class="import-progress hidden">
                <div class="progress-bar-container">
                    <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <p class="import-status"></p>
            </div>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Start Import', 'school-manager-import-export'); ?>
                </button>
                <span class="spinner"></span>
            </p>
        </form>
    </div>
    
    <div class="import-export-section">
        <h2><?php esc_html_e('Export Students', 'school-manager-import-export'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('export_students', 'export_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="export_format"><?php esc_html_e('Format', 'school-manager-import-export'); ?></label>
                    </th>
                    <td>
                        <select name="export_format" id="export_format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="export_students" class="button button-primary">
                    <?php esc_html_e('Export Students', 'school-manager-import-export'); ?>
                </button>
            </p>
        </form>
    </div>
    
    <div class="import-export-section">
        <h2><?php esc_html_e('Documentation', 'school-manager-import-export'); ?></h2>
        
        <h3><?php esc_html_e('CSV Format', 'school-manager-import-export'); ?></h3>
        <p><?php esc_html_e('The CSV file should contain the following columns:', 'school-manager-import-export'); ?></p>
        
        <ul>
            <li><code>first_name</code> - <?php esc_html_e('Student first name (required)', 'school-manager-import-export'); ?></li>
            <li><code>last_name</code> - <?php esc_html_e('Student last name (required)', 'school-manager-import-export'); ?></li>
            <li><code>email</code> - <?php esc_html_e('Student email address (required, must be unique)', 'school-manager-import-export'); ?></li>
            <li><code>username</code> - <?php esc_html_e('Username (optional, will be generated if not provided)', 'school-manager-import-export'); ?></li>
            <li><code>password</code> - <?php esc_html_e('Password (optional, will be generated if not provided)', 'school-manager-import-export'); ?></li>
            <li><code>class_id</code> - <?php esc_html_e('ID of the class to assign the student to', 'school-manager-import-export'); ?></li>
            <li><code>status</code> - <?php esc_html_e('Student status (active, inactive, pending)', 'school-manager-import-export'); ?></li>
        </ul>
        
        <p>
            <a href="#" id="generate-test-csv" class="button button-secondary">
                <?php esc_html_e('Download Sample CSV', 'school-manager-import-export'); ?>
            </a>
        </p>
    </div>
</div>
