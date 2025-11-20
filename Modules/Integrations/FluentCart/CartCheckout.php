<?php

namespace FluentCommunity\Modules\Integrations\FluentCart;

use FluentCart\App\Modules\Templating\AssetLoader;
use FluentCommunity\App\App;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Theming\TemplateLoader;
use function Avifinfo\read;

class CartCheckout
{

    public function register()
    {
        add_action('fluent_community/rendering_path_ssr_checkout', [$this, 'renderNativeCheckout'], 10);

        add_action('fluent_cart/checkout/prepare_other_data', function ($event) {
            $order = Arr::get($event, 'order');
            $requestData = Arr::get($event, 'request_data', []);
            if (!$order || empty($requestData['_fcom_space_id'])) {
                return;
            }

            $prevConfig = $order->config;
            $prevConfig['_fcom_space_id'] = intval($requestData['_fcom_space_id']);
            $order->config = $prevConfig;
            $order->save();
        });

        add_action('fluent_cart/receipt/thank_you/after_header_title', function ($event) {
            $order = Arr::get($event, 'order');
            if (!$order || empty($order->config['_fcom_space_id']) || $order->payment_status !== 'paid') {
                return;
            }

            $spaceId = intval($order->config['_fcom_space_id']);
            $space = BaseSpace::query()->onlyMain()->find($spaceId);

            if (!$space) {
                return;
            }

            ?>
            <style>
                .fcom_checkout_thank_you_box {
                    text-align: center;
                }

                .fcom_checkout_thank_you_box p {
                    margin: 0;
                    color: #000;
                    font-size: 1em;
                }
            </style>
            <div class="fcom_checkout_thank_you_box">
                <p>
                    <?php
                    if ($space->type === 'course') {
                        printf(
                        /* translators: 1: space name, 2: call to action */
                            esc_html__('You have successfully joined the %1$s. %2$s', 'fluent-community'),
                            esc_html($space->title),
                            '<br /><a href="' . esc_url($space->getPermalink()) . '">' . esc_html__('Go to the course', 'fluent-community') . '</a>'
                        );
                    } else {
                        printf(
                        /* translators: 1: space name, 2: call to action */
                            esc_html__('You have successfully joined the %1$s. %2$s', 'fluent-community'),
                            esc_html($space->title),
                            '<br /><a href="' . esc_url($space->getPermalink()) . '">' . esc_html__('Go to the space', 'fluent-community') . '</a>'
                        );
                    }
                    ?>
                </p>
            </div>
            <?php
        });

    }

    public function renderNativeCheckout($parts = [])
    {
        add_filter('pre_get_document_title', function ($title) {
            return __('Checkout', 'fluent-community');
        });

        if (isset($_REQUEST['space_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            add_action('fluent_cart/checkout_form_opening', function () {
                $spaceId = intval($_REQUEST['space_id'] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                ?>
                <input type="hidden" name="_fcom_space_id" value="<?php echo esc_attr($spaceId); ?>"/>
                <?php
            });
        }


        AssetLoader::loadCartAssets();
        remove_all_actions('fluent_community/theme_content', 10);
        add_action('fluent_community/theme_content', function () {
            $cart = \FluentCart\App\Helpers\CartHelper::getCart();
            ?>
            <div class="fcom_single_layout fcom_max_layout feeds">
                <div class="no_bg_layout fhr_content_layout">
                    <div style="max-width: 1080px;padding: 0 2rem 2rem; margin: 2rem auto;"
                         class="fhr_content_layout_body">
                        <h2 class="fcom_checkout_title">Checkout</h2>
                        <div class="fcom_checkout_body">
                            <?php echo do_shortcode('[fluent_cart_checkout]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        });

        add_action('wp_head', function () {
            if (!did_action('pre_get_document_title')) {
                echo '<title>' . esc_html__('Checkout', 'fluent-community') . ' - ' . esc_html(get_bloginfo('name')) . '</title>';
            }

            ?>
            <style>
                .fcom_checkout_body, .fct_address_modal {
                    --fct-checkout-primary-text-color: var(--fcom-primary-text, #606266);
                    --fct-checkout-summary-bg-color: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-secondary-text-color: var(--fcom-secondary-text, #959595);
                    --fct-checkout-border-color: var(--fcom-primary-border, #e3e8ee);
                    --fct-checkout-active-border-color: var(--fcom-secondary-border, #2B2E33);
                    --fct-checkout-primary-bg-color: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-secondary-bg-color: var(--fcom-secondary-bg, #f0f0f1);
                    --fct-checkout-btn-bg-color: var(--fcom-primary-button, #2B2E33);
                    --fct-checkout-btn-text-color: var(--fcom-primary-button-text, #FFFFFF);
                    --fct-btn-border-color: var(--fcom-primary-button, #2B2E33);
                    --fct-checkout-input-bg-color: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-input-border-color: var(--fcom-primary-border, #e3e8ee);
                    --fct-checkout-input-text-color: var(--fcom-primary-text, #606266);
                    --fct-checkout-input-disabled-bg-color: var(--fcom-active-bg, #f0f0f1);
                    --fct-input-bg-color: var(--fcom-primary-bg, #FFFFFF);
                    --fct-input-text-color: var(--fcom-primary-text, #606266);
                    --fct-input-placeholder-text-color: var(--fcom-secondary-text, #959595);
                    --fct-input-disabled-bg-color: var(--fcom-secondary-bg, #f0f0f1);
                    --fct-secondary-text-color: var(--fcom-secondary-text, #959595);
                    --fct-select-dropdown-bg: var(--fcom-primary-bg, #FFFFFF);
                    --fct-select-option-hover-bg: var(--fcom-secondary-bg, #f0f0f1);
                    --fct-checkout-input-placeholder-text-color: var(--fcom-secondary-text, #959595);
                    --fct-checkout-payment-method-bg-color: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-checkbox-bg: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-address-wrapper-bg: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-shipping-methods-bg: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-address-modal-bg: var(--fcom-primary-bg, #FFFFFF);
                    --fct-checkout-address-active-border-color: var(--fcom-secondary-border, #2B2E33);
                }

            </style>
            <?php

        }, 999);

        (new TemplateLoader())->loadScriptsAndStyles();

        status_header(200);
        require_once FLUENT_COMMUNITY_PLUGIN_DIR . 'Modules/Theming/templates/fluent-community-frame-full.php';
        exit();
    }
}
