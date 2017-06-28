<?php
/**
 * @copyright Copyright © 2017 METMEER. All rights reserved.
 * @author    support@metmeer.nl
 */

namespace METMEER\UrlKeyFix\Plugin;

use Magento\CatalogUrlRewrite\Model\Category\ChildrenUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;

/**
 * Class ProductUrlPathGeneratorPlugin
 *
 * When a category contains a child category and a product with the same URL key, Magento 2.1.7 does not detect this.
 * This results in 'URL key for specified store already exists.' errors when saving the category via the admin panel.
 * Using this plugin, the problem is detected, and '-1' is added to the URL of the child product in that category.
 *
 * @see ProductUrlPathGenerator
 */
class ProductUrlPathGeneratorPlugin
{
    /**
     * @var ChildrenUrlRewriteGenerator
     */
    protected $childrenUrlRewriteGenerator;

    /**
     * @var int|null
     */
    protected $storeId;

    /**
     * @var int
     */
    protected $lastProductId;

    /**
     * @var array
     */
    protected $resultCache;

    /**
     * @param ChildrenUrlRewriteGenerator $childrenUrlRewriteGenerator
     */
    public function __construct(
        ChildrenUrlRewriteGenerator $childrenUrlRewriteGenerator
    ) {
        $this->childrenUrlRewriteGenerator = $childrenUrlRewriteGenerator;
    }

    /**
     * Retrieve Product Url path (with category if exists)
     *
     * @param ProductUrlPathGenerator $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\Category $category
     * @return string
     * @see ProductUrlPathGenerator::getUrlPath()
     */
    public function aroundGetUrlPath(
        ProductUrlPathGenerator $subject,
        \Closure $proceed,
        $product,
        $category = null
    ) {
        $result = $proceed($product, $category);

        if (null !== $category) {
            $storeId = $this->storeId ?: $product->getStoreId();

            // cache results for the last product because getUrlPath is called multiple times
            if ($this->lastProductId !== $product->getId()) {
                $this->lastProductId = $product->getId();
                $this->resultCache = [];
            }

            $cacheKey = sprintf('%0d:%0d:%s', $storeId, $category->getId(), $result);
            if (isset($this->resultCache[$cacheKey])) {
                return $this->resultCache[$cacheKey];
            }

            // get all url rewrites for child categories of the products parent category
            $otherUrlRewrites = $this->childrenUrlRewriteGenerator->generate($storeId, $category);

            if ($this->inUrlRewrites($storeId, $result, $otherUrlRewrites)) {
                $suffix = '-' . ($counter = 1);
                while ($this->inUrlRewrites($storeId, $result . $suffix, $otherUrlRewrites)) {
                    $suffix = '-' . (++$counter);
                }
                $result = $result . $suffix;

                // prevent generating a 301 redirect as that would still cause errors
                $product->setData('save_rewrites_history', false);
            }

            $this->resultCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Returns TRUE if the store ID and request path are in the given URL Rewrites array.
     *
     * @param int $storeId
     * @param string $requestPath
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $urlRewrites
     * @return bool
     */
    protected function inUrlRewrites($storeId, $requestPath, $urlRewrites)
    {
        foreach ($urlRewrites as $urlRewrite) {
            if ($urlRewrite->getStoreId() != $storeId) {
                continue;
            }
            if ($urlRewrite->getRequestPath() != $requestPath) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * Retrieve Product Url path with suffix
     *
     * @param ProductUrlPathGenerator $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @param \Magento\Catalog\Model\Category $category
     * @return string
     * @see ProductUrlPathGenerator::getUrlPathWithSuffix()
     */
    public function aroundGetUrlPathWithSuffix(
        ProductUrlPathGenerator $subject,
        \Closure $proceed,
        $product,
        $storeId,
        $category = null
    ) {
        $this->storeId = $storeId;
        $result = $proceed($product, $storeId, $category);
        $this->storeId = null;
        return $result;
    }
}
