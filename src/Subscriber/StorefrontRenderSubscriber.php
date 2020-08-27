<?php declare(strict_types=1);

namespace Wbm\TagManagerEcomm\Subscriber;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wbm\TagManagerEcomm\Cookie\CustomCookieProvider;
use Wbm\TagManagerEcomm\Services\DataLayerModules;
use Wbm\TagManagerEcomm\Services\DataLayerRenderer;

class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    /**
     * @var DataLayerModules
     */
    private $modules;

    /**
     * @var DataLayerRenderer
     */
    private $dataLayerRenderer;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        DataLayerModules $modules,
        DataLayerRenderer $dataLayerRenderer,
        SystemConfigService $systemConfigService
    ) {
        $this->modules = $modules;
        $this->dataLayerRenderer = $dataLayerRenderer;
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onRender',
        ];
    }

    public function onRender(StorefrontRenderEvent $event): void
    {
        $containerId = $this->systemConfigService->get('WbmTagManagerEcomm.config.containerId', $event->getSalesChannelContext()->getSalesChannel()->getId()); // $this->modules->getContainerId();
        $isActive = !empty($containerId) && !$this->systemConfigService->get('WbmTagManagerEcomm.config.isInactive', $event->getSalesChannelContext()->getSalesChannel()->getId()); // $this->modules->isActive();
        $route = $event->getRequest()->attributes->get('_route');

        if (!$isActive) {
            return;
        }

        $cookie = $event->getRequest()->cookies->get(KernelEventsSubscriber::COOKIE_NAME);
        $enabledCookie = $event->getRequest()->cookies->get(CustomCookieProvider::WBM_GTM_ENABLED_COOKIE_NAME);

        if ($cookie) {
            if (in_array($route, $this->modules->getResponseRoutes(), true)) {
                $dataLayer = $cookie;
            }
        } else {
            $parameters = $event->getParameters();

            $modules = $this->modules->getModules();

            if (array_key_exists($route, $modules)) {
                $dataLayer = $this->dataLayerRenderer->setVariables($route, $parameters)
                    ->renderDataLayer($route)
                    ->getDataLayer($route);
            }
        }

        if (!$event->getRequest()->isXmlHttpRequest()) {
            $event->setParameter(
                'gtmContainerId',
                $containerId
            );
            $event->setParameter(
                'isTrackingProductClicks',
                $this->modules->isTrackingProductClicks()
            );
            $event->setParameter(
                'wbmGtmCookieEnabled',
                true // $enabledCookie
            );

            if (!empty($dataLayer)) {
                $event->setParameter(
                    'dataLayer',
                    $dataLayer
                );
            }
        }
    }
}
