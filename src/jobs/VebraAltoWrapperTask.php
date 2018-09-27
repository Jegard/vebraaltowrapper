<?php
/**
 * Vebra Alto Wrapper plugin for Craft CMS 3.x
 *
 * Integration with the estate agency software vebraalto.com
 *
 * @link      https://github.com/Jegard
 * @copyright Copyright (c) 2018 Luca Jegard
 */

namespace jegardvebra\vebraaltowrapper\jobs;

use jegardvebra\vebraaltowrapper\VebraAltoWrapper;

use Craft;
use craft\queue\BaseJob;

/**
 * VebraAltoWrapperTask job
 *
 * Jobs are run in separate process via a Queue of pending jobs. This allows
 * you to spin lengthy processing off into a separate PHP process that does not
 * block the main process.
 *
 * You can use it like this:
 *
 * use jegardvebra\vebraaltowrapper\jobs\VebraAltoWrapperTask as VebraAltoWrapperTaskJob;
 *
 * $queue = Craft::$app->getQueue();
 * $jobId = $queue->push(new VebraAltoWrapperTaskJob([
 *     'description' => Craft::t('vebra-alto-wrapper', 'This overrides the default description'),
 *     'someAttribute' => 'someValue',
 * ]));
 *
 * The key/value pairs that you pass in to the job will set the public properties
 * for that object. Thus whatever you set 'someAttribute' to will cause the
 * public property $someAttribute to be set in the job.
 *
 * Passing in 'description' is optional, and only if you want to override the default
 * description.
 *
 * More info: https://github.com/yiisoft/yii2-queue
 *
 * @author    Luca Jegard
 * @package   VebraAltoWrapper
 * @since     1.0.0
 */
class VebraAltoWrapperTask extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * Some attribute
     *
     * @var string
     */
    public $someAttribute = 'Some Default';
    public $criteria;

    // Public Methods
    // =========================================================================

    /**
     * When the Queue is ready to run your job, it will call this method.
     * You don't need any steps or any other special logic handling, just do the
     * jobs that needs to be done here.
     *
     * More info: https://github.com/yiisoft/yii2-queue
     */
    public function execute($queue)
    {   
        //$this->setProgress($queue, $currentElement++ / $totalElements);
        // Do work here
        //file_put_contents(__DIR__ . '/test.txt', json_encode( $this->criteria['sectionId'] ) );
        //VebraAltoWrapper::getInstance()->vebraAlto->
        $sectionId = $this->criteria['sectionId'];
        $branch = $this->criteria['branch'];
        $token = VebraAltoWrapper::getInstance()->vebraAlto->getToken();

        $update = VebraAltoWrapper::getInstance()->vebraAlto->populateSection( $sectionId, $branch );
        if( $update ){
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Finished import'));
        }else{
            Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Error importing'));
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns a default description for [[getDescription()]], if [[description]] isn’t set.
     *
     * @return string The default task description
     */
    protected function defaultDescription(): string
    {
        return Craft::t('vebra-alto-wrapper', 'Syncing all properties');
    }
}
