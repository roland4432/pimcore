<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\CoreBundle\EventListener\Frontend;

use Pimcore\Bundle\CoreBundle\EventListener\Traits\PimcoreContextAwareTrait;
use Pimcore\Http\Request\Resolver\DocumentResolver;
use Pimcore\Http\Request\Resolver\EditmodeResolver;
use Pimcore\Http\Request\Resolver\PimcoreContextResolver;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class GlobalTemplateVariablesListener implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PimcoreContextAwareTrait;

    /**
     * @var DocumentResolver
     */
    protected $documentResolver;

    /**
     * @var EditmodeResolver
     */
    protected $editmodeResolver;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var array
     */
    protected $globalsStack = [];

    public function __construct(
        DocumentResolver $documentResolver,
        EditmodeResolver $editmodeResolver,
        Environment $twig
    ) {
        $this->documentResolver = $documentResolver;
        $this->editmodeResolver = $editmodeResolver;
        $this->twig = $twig;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 90], // has to be after DocumentFallbackListener
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        if (!$this->matchesPimcoreContext($request, PimcoreContextResolver::CONTEXT_DEFAULT)) {
            return;
        }

        $globals = $this->twig->getGlobals();
        array_push($this->globalsStack, $globals);

        $document = $this->documentResolver->getDocument($request);
        $editmode = $this->editmodeResolver->isEditmode($request);

        $this->twig->addGlobal('document', $document);
        $this->twig->addGlobal('editmode', $editmode);
    }

    /**
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (count($this->globalsStack)) {
            $globals = array_pop($this->globalsStack);
            $this->twig->addGlobal('document', $globals['document'] ?? null);
            $this->twig->addGlobal('editmode', $globals['editmode'] ?? null);
        }
    }
}
