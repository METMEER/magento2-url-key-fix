<?php
/**
 * @copyright Copyright © 2017 METMEER. All rights reserved.
 * @author    support@metmeer.nl
 */

namespace METMEER\UrlKeyFix\Plugin;

use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Class UrlPersistPlugin
 *
 * This plugin performs a final check before saving URL rewrites into the database. If multiple URL rewrites were
 * generated with the same request path for the same store ID, only the first one is preserved. This should fix
 * any remaining 'URL key for specified store already exists.' errors.
 *
 * @see UrlPersistInterface
 */
class UrlPersistPlugin
{
    /**
     * @param UrlPersistInterface $subject
     * @param UrlRewrite[] $urls
     * @return array
     * @see UrlPersistInterface::replace()
     */
    public function beforeReplace($subject, $urls)
    {
        $uniqueKeys = [];
        foreach ($urls as $key => $url) {
            $uniqueKey = sprintf('%0d:%s', $url->getStoreId(), $url->getRequestPath());
            if (isset($uniqueKeys[$uniqueKey])) {
                unset($urls[$key]);
                continue;
            }
            $uniqueKeys[$uniqueKey] = $url;
        }

        return [$urls];
    }
}
