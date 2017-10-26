<?php
/**
 * donations-free plugin for Craft CMS 3.x
 *
 * Free Braintree Donation System
 *
 * @link      https://endurant.org
 * @copyright Copyright (c) 2017 endurant
 */

namespace endurant\donationsfree\controllers;

use Braintree\ClientToken;
use endurant\donationsfree\DonationsFree;

use Craft;
use craft\web\Controller;
use craft\helpers\ArrayHelper;
use endurant\donationsfree\DonationsFreeAssetBundle;
use endurant\donationsfree\errors\DonationsPluginException;
use endurant\donationsfree\models\forms\DonateForm;
use endurant\donationsfree\models\Log;
use endurant\donationsfree\records\Country;
use endurant\donationsfree\records\State;
use endurant\donationsfree\services\LogService;

/**
 * Donate Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    endurant
 * @package   Donationsfree
 * @since     1.0.0
 */
class DonationController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'pay', 'donate'];

    // Public Methods
    // =========================================================================

    public function beforeAction($action)
    {
        // ...set `$this->enableCsrfValidation` here based on some conditions...
        // call parent method that will check CSRF if such property is true.
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/donations-free/donate
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $countries = ArrayHelper::toArray(Country::find()->all());
        $states = ArrayHelper::toArray(State::find()->all());
        $amount = Craft::$app->session->get('donation')['amount'];

        $view = $this->getView();

        $view->setTemplatesPath($this->getViewPath());
        $view->resolveTemplate('index');

        // Include all the JS and CSS stuff
        $view->registerAssetBundle(DonationsFreeAssetBundle::class);

        return $this->renderTemplate('index', [
            'amount' => $amount,
            'countries' => $countries,
            'states' => $states,
            'btAuthorization' => DonationsFree::$PLUGIN->braintreeHttpClient->generateToken()
        ]);
    }

    /**
     * Handle a request going to our plugin's actionDoSomething URL,
     * e.g.: actions/donations-free/donate/do-something
     *
     * @return mixed
     */
    public function actionPay()
    {
        $this->requirePostRequest();

        try {
            DonationsFree::$PLUGIN->donationService->donate(Craft::$app->request->post());
        } catch (DonationsPluginException $e) {
            return json_encode([$e->getTraceAsString(), $e->getMessage(), Craft::$app->request->post()]);
        } catch (\Exception $e) {
            return json_encode([$e->getTraceAsString(), $e->getMessage(), Craft::$app->request->post()]);
        } catch (\Error $e) {
            return json_encode([$e->getTraceAsString(), $e->getMessage(), Craft::$app->request->post()]);
        }

        return json_encode(true);
    }

    /**
     * Handle a request going to our plugin's actionDoSomething URL,
     * e.g.: actions/donations-free/donate/do-something
     *
     * @return mixed
     */
    public function actionDonate()
    {
        $this->requirePostRequest();

        $donateForm = new DonateForm();
        $donateForm->setAttributes(Craft::$app->request->post());

        if (!$donateForm->validate()) {
            return $donateForm->getErrors();
        }

        Craft::$app->session->set('donation', $donateForm);

        return $this->redirect('/actions/donations-free/donation/index');
    }
}
