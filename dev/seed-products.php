<?php
/**
 * Creates test products (with images) using the Magento application bootstrap.
 * Run inside the container: php seed-products.php
 */

use Magento\Framework\App\Bootstrap;

// Navigate up from dev/ to Magento root: dev -> Paystack -> Pstk -> code -> app -> html
$magentoRoot = dirname(__DIR__, 5);
require $magentoRoot . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
try {
    $state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
} catch (\Exception $e) {
    // Area code already set
}

// Path where product images are mounted inside the container
$imgDir = '/var/www/html/app/code/Pstk/Paystack/dev/img/products';

// --- 1. Create a "Shop" category under root category (id=2) ---

$categoryFactory = $objectManager->get(\Magento\Catalog\Model\CategoryFactory::class);
$categoryRepository = $objectManager->get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
$categoryCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class)->create();

$existing = $categoryCollection
    ->addAttributeToFilter('name', 'Shop')
    ->addAttributeToFilter('parent_id', 2)
    ->getFirstItem();

if ($existing && $existing->getId()) {
    $categoryId = (int)$existing->getId();
    echo "Category 'Shop' already exists (ID: $categoryId)\n";
} else {
    $category = $categoryFactory->create();
    $category->setName('Shop');
    $category->setParentId(2);
    $category->setIsActive(true);
    $category->setIncludeInMenu(true);
    $category->setPath('1/2');

    $parentCategory = $categoryRepository->get(2);
    $category->setPath($parentCategory->getPath());

    $categoryRepository->save($category);
    $categoryId = (int)$category->getId();
    echo "Created category 'Shop' (ID: $categoryId)\n";
}

// --- 2. Create 5 test products ---

$productFactory = $objectManager->get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$stockRegistry = $objectManager->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
$categoryLinkManagement = $objectManager->get(\Magento\Catalog\Api\CategoryLinkManagementInterface::class);

$products = [
    ['sku' => 'paystack-tshirt',       'name' => 'Paystack T-Shirt',      'price' => 5000,  'image' => 'paystack-t-shirt.png'],
    ['sku' => 'paystack-hoodie',       'name' => 'Paystack Hoodie',       'price' => 15000, 'image' => 'paystack-hoodie.png'],
    ['sku' => 'paystack-cap',          'name' => 'Paystack Cap',          'price' => 3000,  'image' => 'paystack-cap.png'],
    ['sku' => 'paystack-sticker-pack', 'name' => 'Paystack Sticker Pack', 'price' => 500,   'image' => 'paystack-stickers.png'],
    ['sku' => 'paystack-water-bottle', 'name' => 'Paystack Water Bottle', 'price' => 7500,  'image' => 'paystack-water-bottle.png'],
];

foreach ($products as $p) {
    try {
        $existing = $productRepository->get($p['sku']);
        echo "  Already exists: {$p['name']} (SKU: {$p['sku']})\n";
        continue;
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        // Product doesn't exist, create it
    }

    $product = $productFactory->create();
    $product->setSku($p['sku']);
    $product->setName($p['name']);
    $product->setPrice($p['price']);
    $product->setAttributeSetId(4);
    $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
    $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
    $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
    $product->setWeight(1);

    // Add product image if available
    $imagePath = $imgDir . '/' . $p['image'];
    if (file_exists($imagePath)) {
        // Copy to Magento's import directory (addImageToMediaGallery moves from there)
        $importDir = BP . '/pub/media/import';
        if (!is_dir($importDir)) {
            mkdir($importDir, 0775, true);
        }
        $destPath = $importDir . '/' . $p['image'];
        copy($imagePath, $destPath);

        $product->addImageToMediaGallery(
            $destPath,
            ['image', 'small_image', 'thumbnail'],
            false,
            false
        );
        echo "  Image: {$p['image']}\n";
    } else {
        echo "  No image found at: {$imagePath}\n";
    }

    $product = $productRepository->save($product);

    // Set stock
    $stockItem = $stockRegistry->getStockItemBySku($p['sku']);
    $stockItem->setQty(100);
    $stockItem->setIsInStock(true);
    $stockRegistry->updateStockItemBySku($p['sku'], $stockItem);

    // Assign to Shop category
    $categoryLinkManagement->assignProductToCategories($p['sku'], [$categoryId]);

    echo "  Created: {$p['name']} — NGN " . number_format($p['price']) . "\n";
}

// --- 3. Update homepage CMS to show products ---

$pageRepository = $objectManager->get(\Magento\Cms\Api\PageRepositoryInterface::class);
$searchCriteriaBuilder = $objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

$searchCriteria = $searchCriteriaBuilder
    ->addFilter('identifier', 'home')
    ->create();

$pages = $pageRepository->getList($searchCriteria);
$items = $pages->getItems();

$widgetContent = '<div class="page-main"><h2>Welcome to the Paystack Test Store</h2>'
    . '{{widget type="Magento\CatalogWidget\Block\Product\ProductsList" title="Our Products" products_count="5" template="Magento_CatalogWidget::product/widget/content/grid.phtml" conditions_encoded="^[`1`:^[`type`:`Magento||CatalogWidget||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^]^]"}}'
    . '</div>';

if (count($items) > 0) {
    $homePage = array_values($items)[0];
    $homePage->setContent($widgetContent);
    $pageRepository->save($homePage);
    echo "\nUpdated homepage to display products.\n";
} else {
    echo "\nNote: Could not find homepage CMS page. Navigate to /shop.html to see products.\n";
}

echo "\nDone! Products are available at:\n";
echo "  Homepage:  http://localhost:8080\n";
echo "  Category:  http://localhost:8080/shop.html\n";
