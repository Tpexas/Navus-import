<?php
/*
Plugin Name: Navus Import
Description: Produktų importavimas
Version: 1.0
Author: Edvinas
*/

//padidinama atmintis, nustatomas neribotas veikimo laikas
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0);

add_action('admin_menu', 'add_navus_import_submenu');
add_action('admin_init', 'handle_xml_import');

function add_navus_import_submenu() {
  add_submenu_page(
    'woocommerce',
    'Navus Import',
    'Navus Import',
    'manage_options',
    'navus-import',
    'render_navus_import_page'
  );
}

function render_navus_import_page() {
  ?>
  <div class="wrap">
    <h1>Produktų importavimas</h1>
    <form method="post" enctype="multipart/form-data">
    <input type="file" name="xml_file" accept=".xml" required>
      <?php wp_nonce_field('xml_import', 'xml_import_nonce'); ?>
      <?php submit_button('Importuoti XML'); ?>
    </form>
  </div>
  <?php
}

function handle_xml_import() {
  if (isset($_FILES['xml_file'])) {
    if (!isset($_POST['xml_import_nonce']) || !wp_verify_nonce($_POST['xml_import_nonce'], 'xml_import')) {
      wp_die('Invalid nonce.');
    }

    $xml_file = $_FILES['xml_file']['tmp_name'];
    $xml = simplexml_load_file($xml_file);

    foreach ($xml->Product as $product) {
      $price = (float) $product->Retail_Price;
      $quantity = (int) $product->Inventory_Count;
      $name = (string) $product->Name;
      $description = (string) $product->Description;

      $existing_products = get_posts(array(
        'title' => $name,
        'post_type' => 'product',
        'post_status' => 'publish',
        'numberposts' => 1,
    ));

      if ($existing_products) {
        //jei produktas su nurodytu pavadinimu egzistuoja, atnaujinama kaina, likutis
        $existing_product = $existing_products[0];
        update_post_meta($existing_product->ID, '_price', $price);
        update_post_meta($existing_product->ID, '_regular_price', $price);
        update_post_meta($existing_product->ID, '_stock', $quantity);
        update_post_meta($existing_product->ID, '_manage_stock', 'yes');
      } else {
        //jei neegzistuoja, sukuriamas naujas produktas
        $new_product = new WC_Product_Simple();
        $new_product->set_name($name);
        $new_product->set_regular_price($price);
        $new_product->set_price($price);
        $new_product->set_stock_quantity($quantity);
        $new_product->set_description($description);
        $new_product->set_manage_stock(true);
        $new_product->save();
      }
    }
    echo '<div class="updated"><p>Įkėlimas atliktas!</p></div>';
  }
}

