<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShopBundle\Controller\Admin\Sell\Catalog;

use Exception;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Command\BulkDeleteCartRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Command\DeleteCartRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Query\SearchCartRules;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\CartRuleGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Search\Filters\CartRuleFilters;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use PrestaShopBundle\Security\Annotation\DemoRestricted;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responsible for Cart rules (a.k.a cart discounts/vouchers) actions in Back Office
 */
class CartRuleController extends FrameworkBundleAdminController
{
    /**
     * Displays cart rule listing page.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param Request $request
     *
     * @param CartRuleFilters $cartRuleFilters
     * @return Response
     */
    public function indexAction(
        Request $request,
        CartRuleFilters $cartRuleFilters
    ): Response
    {
        $cartRuleGridFactory = $this->get('prestashop.core.grid.grid_factory.cart_rule');
        $cartRuleGrid = $cartRuleGridFactory->getGrid($cartRuleFilters);

        return $this->render('@PrestaShop/Admin/Sell/Catalog/CartRule/index.html.twig', [
            'enableSidebar' => true,
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
            'cartRuleGrid' => $this->presentGrid($cartRuleGrid),
        ]);
    }

    /**
     * Searches for cart rules by provided search phrase
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller')) || is_granted('create', 'AdminOrders')")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function searchAction(Request $request): JsonResponse
    {
        $searchPhrase = $request->query->get('search_phrase');
        $cartRules = [];

        if ($searchPhrase) {
            try {
                $cartRules = $this->getQueryBus()->handle(new SearchCartRules($searchPhrase));
            } catch (Exception $e) {
                return $this->json(
                    ['message' => $this->getFallbackErrorMessage(get_class($e), $e->getCode())],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }

        return $this->json([
            'cart_rules' => $cartRules,
        ]);
    }

    /**
     * Provides filters functionality
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @return RedirectResponse
     */
    public function searchGridAction(Request $request): RedirectResponse
    {
        $gridDefinitionFactory = 'prestashop.core.grid.definition.factory.cart_rule';
        $filterId = CartRuleGridDefinitionFactory::GRID_ID;
        if ($request->request->has(CartRuleGridDefinitionFactory::GRID_ID)) {
            $gridDefinitionFactory = 'prestashop.core.grid.definition.factory.cart_rule';
            $filterId = CartRuleGridDefinitionFactory::GRID_ID;
        }

        /** @var ResponseBuilder $responseBuilder */
        $responseBuilder = $this->get('prestashop.bundle.grid.response_builder');

        return $responseBuilder->buildSearchResponse(
            $this->get($gridDefinitionFactory),
            $request,
            $filterId,
            'admin_cart_rules_index'
        );
    }

    /**
     * Deletes cart rule
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", redirectRoute="admin_cart_rules_index")
     * @DemoRestricted(redirectRoute="admin_cart_rules_index")
     *
     * @param $cartRuleId
     *
     * @return RedirectResponse
     */
    public function deleteAction($cartRuleId): RedirectResponse
    {
        try {
            $this->getCommandBus()->handle(new DeleteCartRuleCommand((int) $cartRuleId));
            $this->addFlash(
                'success',
                $this->trans('Successful deletion.', 'Admin.Notifications.Success')
            );
        } catch (Exception $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages()));
        }

        return $this->redirectToRoute('admin_cart_rules_index');
    }

    /**
     * Deletes cartRules on bulk action
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", redirectRoute="admin_cart_rules_index")
     * @DemoRestricted(redirectRoute="admin_cart_rules_index")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function bulkDeleteAction(Request $request): RedirectResponse
    {
        $cartRuleIds = $this->getBulkCartRulesFromRequest($request);

        try {
            $this->getCommandBus()->handle(new BulkDeleteCartRuleCommand($cartRuleIds));
            $this->addFlash(
                'success',
                $this->trans('Successful deletion.', 'Admin.Notifications.Success')
            );
        } catch (Exception $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages()));
        }

        return $this->redirectToRoute('admin_cart_rules_index');
    }

    /**
     * Toggles cart rule status
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute="admin_cart_rules_index                              ")
     * @DemoRestricted(redirectRoute="admin_cart_rules_index")
     *
     * @param int $manufacturerId
     *
     * @return RedirectResponse
     */
    public function toggleStatusAction($cartRuleId)
    {
        return $this->redirectToRoute('admin_cart_rules_index');
    }

    /**
     * Provides cart rule ids from request of bulk action
     *
     * @param Request $request
     *
     * @return array
     */
    private function getBulkCartRulesFromRequest(Request $request): array
    {
        $cartRuleIds = $request->request->get('cart_rule_bulk');

        if (!is_array($cartRuleIds)) {
            return [];
        }

        foreach ($cartRuleIds as &$cartRuleId) {
            $cartRuleId = (int) $cartRuleId;
        }

        return $cartRuleIds;
    }
}
