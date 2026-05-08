<?php
/**
 * Creates test products (with images) using the Magento application bootstrap.
 * Run inside the container: php app/code/Lomi/Payments/dev/seed-products.php
 */

use Magento\Framework\App\Bootstrap;

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

$imgDir = '/var/www/html/app/code/Lomi/Payments/dev/img/products';

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

$productFactory = $objectManager->get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$stockRegistry = $objectManager->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
$categoryLinkManagement = $objectManager->get(\Magento\Catalog\Api\CategoryLinkManagementInterface::class);

$products = [
    ['sku' => 'lomi-tshirt',       'name' => 'lomi. T-Shirt',       'price' => 5000,  'image' => 'lomi-t-shirt.png'],
    ['sku' => 'lomi-hoodie',       'name' => 'lomi. Hoodie',        'price' => 15000, 'image' => 'lomi-hoodie.png'],
    ['sku' => 'lomi-cap',          'name' => 'lomi. Cap',           'price' => 3000,  'image' => 'lomi-cap.png'],
    ['sku' => 'lomi-sticker-pack', 'name' => 'lomi. Sticker Pack',  'price' => 500,   'image' => 'lomi-stickers.png'],
    ['sku' => 'lomi-water-bottle', 'name' => 'lomi. Water Bottle', 'price' => 7500,  'image' => 'lomi-water-bottle.png'],
];

foreach ($products as $p) {
    try {
        $existing = $productRepository->get($p['sku']);
        echo "  Already exists: {$p['name']} (SKU: {$p['sku']})\n";
        continue;
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
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

    $imagePath = $imgDir . '/' . $p['image'];
    if (file_exists($imagePath)) {
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

    $stockItem = $stockRegistry->getStockItemBySku($p['sku']);
    $stockItem->setQty(100);
    $stockItem->setIsInStock(true);
    $stockRegistry->updateStockItemBySku($p['sku'], $stockItem);

    $categoryLinkManagement->assignProductToCategories($p['sku'], [$categoryId]);

    echo "  Created: {$p['name']} — " . number_format($p['price']) . "\n";
}

$pageRepository = $objectManager->get(\Magento\Cms\Api\PageRepositoryInterface::class);
$searchCriteriaBuilder = $objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

$searchCriteria = $searchCriteriaBuilder
    ->addFilter('identifier', 'home')
    ->create();

$pages = $pageRepository->getList($searchCriteria);
$items = $pages->getItems();

$widgetContent = '<div class="page-main"><h2>Welcome to the lomi. test store</h2>'
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
