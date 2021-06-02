<?php

/**
 * Vebra Alto Wrapper plugin for Craft CMS 3.x
 *
 * Integration with the estate agency software vebraalto.com
 *
 * @link      https://github.com/Jegard
 * @copyright Copyright (c) 2018 Luca Jegard
 */

namespace jegardvebra\vebraaltowrapper\controllers;


use jegardvebra\vebraaltowrapper\VebraAltoWrapper;
use jegardvebra\vebraaltowrapper\jobs\VebraAltoWrapperTask;

use Craft;
use craft\web\Controller;

/**
 * Default Controller
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
 * @author    Luca Jegard
 * @package   VebraAltoWrapper
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'update-branch', 'connect'];

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/vebra-alto-wrapper/default
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $result = 'Welcome to the DefaultController actionIndex() method';

        return $result;
    }

    public function actionConnect()
    {
        VebraAltoWrapper::getInstance()->vebraAlto->getToken();
        return 'true';
    }

    public function actionSetSection()
    {
        $sectionId = Craft::$app->getRequest()->getRequiredParam('sectionId');
        $branch = Craft::$app->getRequest()->getRequiredParam('branch');

        if (VebraAltoWrapper::getInstance()->vebraAlto->updateLinkModel($sectionId, $branch)) {
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Saved link settings'));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Error saving settings'));
        }

        return $this->redirect('admin/vebra-alto-wrapper');
    }

    public function actionSetMap()
    {
        $sectionId = Craft::$app->getRequest()->getRequiredParam('sectionId');
        $fieldMapping = Craft::$app->getRequest()->getRequiredParam('fieldMapping');

        if (VebraAltoWrapper::getInstance()->vebraAlto->updateFieldMapping($sectionId, $fieldMapping)) {
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Saved mapping settings'));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Error saving settings'));
        }

        return $this->redirect('admin/vebra-alto-wrapper');
    }
    public function actionDeleteLink()
    {
        $sectionId = Craft::$app->getRequest()->getRequiredParam('sectionId');

        if (VebraAltoWrapper::getInstance()->vebraAlto->deleteLinkModel($sectionId)) {
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Link deleted'));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Error deleting link'));
        }
        return $this->redirect('admin/vebra-alto-wrapper');
    }
    public function actionUpdateAll()
    {
        $list = VebraAltoWrapper::getInstance()->vebraAlto->getPropertyList();
        VebraAltoWrapper::getInstance()->vebraAlto->populateProperties($list);

        return 'Updated all properties';
    }

    public function actionUpdateBranch()
    {
        VebraAltoWrapper::getInstance()->vebraAlto->getToken();
        $sectionId = Craft::$app->getRequest()->getRequiredParam('sectionId');
        $branch = Craft::$app->getRequest()->getRequiredParam('branch');

        VebraAltoWrapper::getInstance()->vebraAlto->vebraLog('Starting new branch update');
        $queue = Craft::$app->getQueue();
        $queue->push(new VebraAltoWrapperTask([
            'criteria' => [
                'sectionId' => $sectionId,
                'branch' => $branch,
            ],
        ]));

        return $this->redirect('admin/vebra-alto-wrapper');
    }
}
