<?php

namespace Lomi\Payments\Model;

use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\DesignInterface;

/**
 * Resolves checkout branding assets (pay-with-lomi image and payment icons).
 */
class CheckoutBranding
{
    private const DEFAULT_ICON_SLUGS = ['wave', 'mtn', 'apple-pay', 'google-pay', 'spi'];

    /** @var AssetRepository */
    private $assetRepository;

    /** @var DesignInterface */
    private $design;

    public function __construct(
        AssetRepository $assetRepository,
        DesignInterface $design
    ) {
        $this->assetRepository = $assetRepository;
        $this->design = $design;
    }

    public function usesCheckoutBrandingCard(): bool
    {
        return true;
    }

    public function getPayWithImageUrl(): string
    {
        return (string) $this->getPaymentIconUrl('pay-with-lomi');
    }

    /**
     * @return string[]
     */
    public function getPaymentIconUrls(): array
    {
        $urls = [];

        foreach (self::DEFAULT_ICON_SLUGS as $slug) {
            $url = $this->getPaymentIconUrl($slug);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function getPaymentIconUrl(string $slug): string
    {
        $extensions = ['webp', 'svg', 'png', 'jpg', 'jpeg'];

        foreach ($extensions as $extension) {
            try {
                $asset = $this->assetRepository->createAsset(
                    'Lomi_Payments::images/' . $slug . '.' . $extension,
                    $this->getAssetContext()
                );
                $url = $asset->getUrl();

                if ($url !== '' && strpos($url, '/_view/') === false) {
                    return $url;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Bind assets to the active storefront theme so URLs resolve after static deploy.
     *
     * @return array<string, string>
     */
    private function getAssetContext(): array
    {
        $this->design->setArea('frontend');

        $theme = $this->design->getDesignTheme();
        $themePath = $theme ? (string) $theme->getThemePath() : '';

        if ($themePath === '' || strpos($themePath, '/') === false) {
            $this->design->setDesignTheme('Magento/luma', 'frontend');
            $theme = $this->design->getDesignTheme();
            $themePath = $theme ? (string) $theme->getThemePath() : 'Magento/luma';
        }

        return [
            'area' => 'frontend',
            'theme' => $themePath,
            'locale' => $this->design->getLocale() ?: 'en_US',
        ];
    }
}
