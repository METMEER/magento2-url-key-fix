<?php
/**
 * @copyright Copyright © 2017 METMEER. All rights reserved.
 * @author    support@metmeer.nl
 */

namespace METMEER\UrlKeyFix\Plugin;

use Magento\Catalog\Controller\Adminhtml\Category\Save;

/**
 * Class AdminhtmlCategorySavePlugin
 *
 * This plugin optimizes category saving via the admin panel. In Magento 2.1.7 when saving a category
 * no category is loaded initially by \Magento\Catalog\Controller\Adminhtml\Category::_initCategory
 * because it tries to get the category ID from parameter 'id' instead of 'entity_id'.
 *
 * This means the URL rewrites are generated again, because the dataHasChangedFor calls in
 * \Magento\CatalogUrlRewrite\Observer\CategoryProcessUrlRewriteSavingObserver::execute always
 * return TRUE.
 *
 * @see Save
 */
class AdminhtmlCategorySavePlugin
{
    /**
     * @param Save $subject
     * @see Save::execute()
     */
    public function beforeExecute($subject)
    {
        $requestParams = $subject->getRequest()->getParams();
        if (!isset($requestParams['id']) || !isset($requestParams['entity_id'])) {
            return;
        }

        if ('' === $requestParams['id']) {
            $requestParams['id'] = $requestParams['entity_id'];
            $subject->getRequest()->setParams($requestParams);
        }
    }
}
