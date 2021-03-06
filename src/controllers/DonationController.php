<?php
/**
 * donate-elite plugin for Craft CMS 3.x
 *
 * Free Braintree Donation System
 *
 * @link      https://endurant.org
 * @copyright Copyright (c) 2017 endurant
 */

namespace infoservio\donateelite\controllers;

use infoservio\donateelite\DonateElite;

use Craft;
use craft\web\Controller;
use craft\helpers\ArrayHelper;
use infoservio\donateelite\assetbundles\bootstrap\DonationsFreeBootstrapAssetBundle;
use infoservio\donateelite\models\forms\DonateForm;
use infoservio\donateelite\records\Country;
use infoservio\donateelite\models\Field;
use infoservio\donateelite\records\BraintreeDonationSettings;
use infoservio\donateelite\records\State;
use infoservio\donateelite\records\Step;

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
    protected $allowAnonymous = ['pay', 'donate', 'success', 'error'];

    // Public Methods
    // =========================================================================

    public function beforeAction($action)
    {
        // ...set `$this->enableCsrfValidation` here based on some conditions...
        // call parent method that will check CSRF if such property is true.
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionSuccess()
    {
        $view = $this->getView();

        $view->setTemplatesPath($this->getViewPath());
        // Include all the JS and CSS stuff
        $view->registerAssetBundle(DonationsFreeBootstrapAssetBundle::class);

        $successMessage = BraintreeDonationSettings::find()->where(['name' => 'successMessage'])->one()->value;

        $resetForm = Craft::$app->session->get('donation');
        if ($stateId = $resetForm['stateId']) {
            $resetForm['state'] = State::find()->where(['id' => $stateId])->one()->name;
        }

        $resetForm['countryId'] = Country::find()->where(['id' => $resetForm['countryId']])->one()->name;
        unset($resetForm['nonce']);

        return $this->renderTemplate('donation-success', [
            'baseUrl' => (Craft::$app->session->get('baseUrl') ? Craft::$app->session->get('baseUrl') : '/'),
            'successMessage' => $successMessage,
            'resetForm' => $resetForm,
        ]);
    }

    public function actionError() 
    {
        $view = $this->getView();

        $view->setTemplatesPath($this->getViewPath());
        // Include all the JS and CSS stuff
        $view->registerAssetBundle(DonationsFreeBootstrapAssetBundle::class);

        $errorMessage = BraintreeDonationSettings::find()->where(['name' => 'errorMessage'])->one()->value;

        return $this->renderTemplate('donation-error', [
            'errorMessage' => $errorMessage,
            'baseUrl' => Craft::$app->session->get('baseUrl') ? Craft::$app->session->get('baseUrl') : '/'
        ]);
    }

    /**
     * Handle a request going to our plugin's actionDoSomething URL,
     * e.g.: actions/donate-elite/donate/do-something
     *
     * @return mixed
     */
    public function actionPay()
    {
        $view = $this->getView();

        $view->setTemplatesPath($this->getViewPath());

        if ($post = Craft::$app->request->post()) {
            $view->resolveTemplate('error');

            try {
                DonateElite::$PLUGIN->donation->donate($post);
            } catch (\Exception $e) {
                return $this->redirect('/actions/donate-elite/donation/error');
            }

            $view->resolveTemplate('success');
            Craft::$app->session->set('donation', $post);
            return $this->redirect('/actions/donate-elite/donation/success');
        }

        $fixed = false;
        $amount = 100;

        $countries = ArrayHelper::toArray(Country::find()->all());
        $states = ArrayHelper::toArray(State::find()->all());
        $fields = Field::getFieldsArr();
        $defaultCountryId = Country::DEFAULT_COUNTRY_ID;
        if ($fixedAmount = Craft::$app->session->get('donationForm')['fixedAmount']) {
            $fixed = true;
        } else {
            $amount = Craft::$app->session->get('donationForm')['amount'];
            $amount = ($amount) ? $amount : 100;
        }

        $projectId = Craft::$app->session->get('donationForm')['projectId'];
        $projectName = Craft::$app->session->get('donationForm')['projectName'];
        $color = BraintreeDonationSettings::find()->where(['name' => 'color'])->one()->value;
        $steps = Step::find()->orderBy('order ASC')->all();

        $view->resolveTemplate('index');

        return $this->renderTemplate('pay', [
            'fixed' => $fixed,
            'amount' => $fixed ? $fixedAmount : $amount,
            'defaultCountryId' => $defaultCountryId,
            'countries' => $countries,
            'states' => $states,
            'btAuthorization' => DonateElite::$PLUGIN->braintreeHttpClient->generateToken(),
            'projectId' => $projectId,
            'projectName' => $projectName,
            'mainColor' => $color,
            'fields' => $fields,
            'steps' => $steps
        ]);
    }

    /**
     * Handle a request going to our plugin's actionDoSomething URL,
     * e.g.: actions/donate-elite/donate/do-something
     *
     * @return mixed
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDonate()
    {
        $this->requirePostRequest();

        $donateForm = new DonateForm();
        $donateForm->setAttributes(Craft::$app->request->post());

        if (!$donateForm->validate()) {
            return $donateForm->getErrors();
        }

        Craft::$app->session->set('donationForm', $donateForm);
        Craft::$app->session->set('baseUrl', Craft::$app->request->baseUrl);

        return $this->redirect('/actions/donate-elite/donation/pay');
    }
}
