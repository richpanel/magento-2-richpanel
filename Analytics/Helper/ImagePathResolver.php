<?php

namespace Richpanel\Analytics\Helper;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ImagePathResolver extends AbstractHelper
{
    /**
     * @var File
     */
    private File $fileDriver;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context $context
     * @param File $fileDriver
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        File $fileDriver,
        DateTime $dateTime
    ) {
        $this->fileDriver = $fileDriver;
        $this->dateTime = $dateTime;
        parent::__construct($context);
    }

    /**
     * Takes the best quality image url with timestamp of last modification
     * @param Product $product store product
     * @return string
     */
    public function getBaseImage(Product $product): string 
    {
        $timestampUrl = '';
        $images = $product->getMediaGalleryImages();
        
        if ($images && $images->count() > 0) {
            $firstImage = $images->getFirstItem();
            $imagePath = (string)$firstImage->getPath();
            $imageUrl = (string)$firstImage->getUrl();
            
            if ($imagePath && $this->fileDriver->isExists($imagePath)) {
                try {
                    $mTime = $this->fileDriver->stat($imagePath)['mtime'] ?? null;
                    if ($mTime && $imageUrl) {
                        $formattedTime = $this->dateTime->date('F d Y H:i:s.', $mTime);
                        $timestamp = $this->dateTime->timestamp($formattedTime);
                        if ($timestamp !== false) {
                            $timestampUrl = $imageUrl . '?t=' . $timestamp;
                        }
                    }
                } catch (\Exception $e) {
                    $this->_logger->error('Error getting image timestamp: ' . $e->getMessage());
                }
            }
        }

        return $timestampUrl;
    }
}