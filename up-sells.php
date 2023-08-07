<?php
/**
 * Single Product Up-Sells
 *
 * @version     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product, $porto_settings;

$upsells = $product->get_upsell_ids();

if ( sizeof( $upsells ) === 0 || empty( $porto_settings['product-upsells'] ) ) {
	return;
}

$meta_query = WC()->query->get_meta_query();

//get related products categories ids
$relatedProductsCategoriesAll = array();
foreach($upsells as $id){
	$relatedProduct = wc_get_product($id);
	$relatedProductsCategories = $relatedProduct->category_ids;
	$relatedProductsCategoriesAll = array_merge($relatedProductsCategoriesAll, $relatedProductsCategories);
}

$uniqueArrayIDs = array_unique($relatedProductsCategoriesAll, SORT_REGULAR); //remove duplicated array values
$duplicatedArrayID = array_unique( array_diff_assoc( $relatedProductsCategoriesAll, $uniqueArrayIDs)); //get the duplicated value

//delete duplicated value from array
if (($key = array_search(array_values($duplicatedArrayID)[0], $uniqueArrayIDs)) !== false) {
    unset($uniqueArrayIDs[$key]);
}
$uniqueArrayIDs = array_values($uniqueArrayIDs);

//get all product categories
$categoryArgs = array(
	'orderby' => 'name',
	'order' => 'ASC',
	'hide_empty' => 0,
    'taxonomy' => 'product_cat',
	'exclude' => array(15, 25)
);
$productCategories = get_categories($categoryArgs);

//get first level (parent) categories
$firstLevelCategoriesIDs = array();
foreach($productCategories as $productCategorie){
	if(!$productCategorie->parent) {
		$firstLevelCategoriesIDs[] = $productCategorie->term_id;
	}
}

//get second level categories
$secondLevelCategoriesIDs = array();
foreach($productCategories as $productCategorie){
	if( in_array( $productCategorie->parent, $firstLevelCategoriesIDs )) {
		$secondLevelCategoriesIDs[] = $productCategorie->term_id;
    }
}

$uniqueArrayIDs = array_intersect($secondLevelCategoriesIDs, $uniqueArrayIDs); //compare unique up-sells products ids with second level categories ids and keep the same values
$uniqueArrayIDs = array_values($uniqueArrayIDs); //reorder the array

//while array has keys show products
$i = 0;
while($i<count($uniqueArrayIDs)){

	$args = array(
		'post_type'           => 'product',
		'ignore_sticky_posts' => 1,
		'no_found_rows'       => 1,
		'posts_per_page'      => $porto_settings['product-upsells-count'],
		'orderby'             => $orderby,
		'post__in'            => $upsells,
		'post__not_in'        => array( $product->get_id() ),
		'meta_query'          => $meta_query,
		//group up-sells based on category id
		'tax_query' => array(
			array(
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => $uniqueArrayIDs[$i]
			)
		)
	);

	$products = new WP_Query( $args );

	if ( $products->have_posts() ) :
		global $porto_woocommerce_loop;

		$porto_woocommerce_loop['columns'] = isset( $porto_settings['product-upsells-cols'] ) ? $porto_settings['product-upsells-cols'] : ( isset( $porto_settings['product-cols'] ) ? $porto_settings['product-cols'] : 3 );

		if ( ! $porto_woocommerce_loop['columns'] ) {
			$porto_woocommerce_loop['columns'] = 4;
		}
		?>

		<div class="upsells products">

			<h2 class="slider-title">
				<!--<span class="inline-title"><?php //esc_html_e( 'You may also like&hellip;', 'woocommerce' ); ?></span><span class="line"></span> -->
				<?php
					//echo category name
					if( $term = get_term_by( 'id', $uniqueArrayIDs[$i], 'product_cat' ) ){ ?>
    					<span class="inline-title"><?php echo $term->name; ?></span>
					<?php }else{
						esc_html_e( 'You may also like&hellip;', 'woocommerce' );
					}
				?>
				<span class="line"></span>
			</h2>

			<div class="slider-wrapper">

				<?php

				$porto_woocommerce_loop['view'] = 'products-slider';

				woocommerce_product_loop_start();
				?>

					<?php
					while ( $products->have_posts() ) :
						$products->the_post();
						?>

						<?php wc_get_template_part( 'content', 'product' ); ?>

					<?php endwhile; // end of the loop. ?>

				<?php
				woocommerce_product_loop_end();
				?>
			</div>

		</div>

		<?php
	endif;

	$i++;
}

wp_reset_postdata();
