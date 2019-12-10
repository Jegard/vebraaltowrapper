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
use craft\elements\Entry;
use craft\queue\BaseJob;
use craft\helpers\FileHelper;
use craft\elements\Category;

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
        $sectionId = $this->criteria['sectionId'];
        $branch = $this->criteria['branch'];
        $token = VebraAltoWrapper::getInstance()->vebraAlto->getToken();

        $branchName = $branch;
        $linkModel = VebraAltoWrapper::getInstance()->vebraAlto->getLinkModel($sectionId);
        $fieldMapping = json_decode( $linkModel->fieldMapping );
        $branches = VebraAltoWrapper::getInstance()->vebraAlto->getBranch();

        if( strpos($branchName, '-noname') !== false ){
            foreach ($branches as $_branch) {
                if ((int)$_branch->branchid == explode('-',$branchName)[0]) {
                    $branch = $_branch;
                }
            }
        }else{
            foreach ($branches as $_branch) {
                if ($_branch->name == $branchName) {
                    $branch = $_branch;
                }
            }
        }

        $propertyList = VebraAltoWrapper::getInstance()->vebraAlto->connect( $branch->url . '/property' )['response']['property'];

        $allProps = [];
        foreach( $propertyList as $propertyKey => $property ){
            $this->setProgress($queue, $propertyKey / count($propertyList));

            $property = VebraAltoWrapper::getInstance()->vebraAlto->connect( $property->url )['response'];
            $property = json_decode(json_encode( $property ), TRUE);

            $allProps = array_merge( $allProps, [ $property ] );

            $title = $property['address']['display'];
            $ref = $property['reference']['software'];
            $this->vebraLog('Adding property ' . $title);

            $fields = array(
                'title' => $title,
                'reference' => $ref,
            );

            foreach($fieldMapping as $craftField => $vebraField){

                switch($vebraField){
                    case 'parish':
                        $this->vebraLog('Creating parish categories');
                        $ids = [];
                        $cats = VebraAltoWrapper::getInstance()->vebraAlto->searchCategoriesByTitle( (string)$property['address']['town'] );
                        foreach($cats as $cat){
                            $ids [] = $cat->id;
                        }
                        //$fields[$craftField] = $ids;
                        $fields[$craftField] = $ids;
                        break;
                    case 'LetOrSale(category)':
                        if( (int)$property['web_status'] > 99 ){
                            //letting
                            $cat = Category::find()
                                ->title('For Let')
                                ->all();
                        }else{
                            //sales
                            $cat = Category::find()
                                ->title('For Sale')
                                ->all();
                        }
                        if( count($cat) > 0 ){
                            $fields[$craftField] = [ $cat[0]->id ];
                        }
                        break;
                    case 'floorplan':
                        $floorplan = VebraAltoWrapper::getInstance()->vebraAlto->getFloorPlan( $property['files'] );
                        $fields[$craftField] = $floorplan;
                        break;
                    case 'pdf':
                            $pdfs = VebraAltoWrapper::getInstance()->vebraAlto->getPdfs( $property['files'] );
                            $fields[$craftField] = $pdfs;
                        break;
                    case 'measurements':
                            $measure = [];

                            // d( $property['paragraphs'] );
                            if( VebraAltoWrapper::getInstance()->vebraAlto->findKey( $property['paragraphs'], 'paragraph' ) ){
                                $paragraphs = $property['paragraphs']['paragraph'];

                                foreach($paragraphs as $paragraph){

                                    if( gettype( $paragraph ) == 'array' ){
                                        

                                        if( VebraAltoWrapper::getInstance()->vebraAlto->findKey( $paragraph, 'metric' ) && VebraAltoWrapper::getInstance()->vebraAlto->findKey( $paragraph, 'name' ) && VebraAltoWrapper::getInstance()->vebraAlto->findKey( $paragraph, 'text' ) ){
                                            $name = $paragraph['name'];
                                            $dimensions = $paragraph['dimensions']['metric'];
                                            $text = $paragraph['text'];

                                            if( gettype( $name ) != 'array' && gettype( $dimensions ) != 'array' && gettype( $text ) != 'array' ){
                                                $measure [] = $paragraph['name'] . ' | ' . $paragraph['dimensions']['metric'] . ' | ' . $paragraph['text'];
                                            }
                                            
                                        }
                                    }
                                }
                                $fields[$craftField] = join('@', $measure);
                            }
                            
                        break;
                        case 'paragraphs':
                            $measure = [];

                            if( VebraAltoWrapper::getInstance()->vebraAlto->findKey( $property['paragraphs'], 'paragraph' ) ){
                                $paragraphs = $property['paragraphs']['paragraph'];

                                foreach($paragraphs as $paragraph){

                                    if( gettype( $paragraph ) == 'array' ){
                                        

                                        if( VebraAltoWrapper::getInstance()->vebraAlto->findKey( $paragraph, 'metric' ) && VebraAltoWrapper::getInstance()->vebraAlto->findKey( $paragraph, 'name' ) && VebraAltoWrapper::getInstance()->vebraAlto->findKey( $paragraph, 'text' ) ){
                                            $name = $paragraph['name'];
                                            $text = $paragraph['text'];

                                            if(gettype( $name ) == 'array'){
                                                $name = 'quote';
                                            }

                                            if(gettype( $text ) != 'array'){
                                                $measure [] = $name . ' | ' . $paragraph['text'];
                                            }
                                        }
                                    }
                                }
                                $fields[$craftField] = join('@', $measure);
                            }
                            
                        break;
                    case 'images':
                            $images = VebraAltoWrapper::getInstance()->vebraAlto->getImages( $property['files'] , $title );
                            $fields[$craftField] = $images;
                        break;
                    default:
                        if( strlen( $vebraField ) > 0 ){
                            $value = VebraAltoWrapper::getInstance()->vebraAlto->getArrayValueByCsv( $vebraField, $property );
                            if( strpos($vebraField, ',') !== false ){
                                // \Kint::dump(  $this->getArrayValueByCsv( $vebraField, $property ) );
                                $value = VebraAltoWrapper::getInstance()->vebraAlto->getArrayValueByCsv( $vebraField, $property );

                                
                            }else{
                                $value = $property[$vebraField];
                            }
                            
                            $fields[$craftField] = is_array( $value ) ? join('|', $value) : $value;
                        }
                }
            }

            $entry = Entry::find()
                ->sectionId($sectionId)
                //->title( $title )
                ->reference( $ref )
                ->status(null)
                ->all();
            
            if( empty( $entry ) )
            {
                $this->vebraLog('Attempting to save entry ' . json_encode($fields));
                VebraAltoWrapper::getInstance()->vebraAlto->saveNewEntry( $sectionId , $fields );
            }else{
                $this->vebraLog('Attempting to update entry ' . json_encode($fields));
                VebraAltoWrapper::getInstance()->vebraAlto->updateEntry( $entry[0] , $fields );
            }
        }
        file_put_contents(__DIR__.'/props.json', json_encode($allProps));
        // d($allProps[0]['paragraphs']['paragraph']  );
        $allEntries = Entry::find()
            ->sectionId($sectionId)
            //->title( $title )
            ->limit(null)
            ->status(null)
            ->all();
        foreach($allEntries as $entry){
            $isOnVebra = false;
            foreach( $allProps as $property ){
                if( (string)$entry->reference == (string)$property['reference']['software'] ){
                    $isOnVebra = true;
                }
            }
            if( !$isOnVebra ){
                $entry->enabled = false;
                Craft::$app->elements->saveElement($entry);
            }else{
                $entry->enabled = true;
                Craft::$app->elements->saveElement($entry);
            }
        }

    }

    public function vebraLog($message)
    {
        $file = Craft::getAlias('@storage/logs/vebra.log');
        $log = date('Y-m-d H:i:s').' '.$message."\n";
        FileHelper::writeToFile($file, $log, ['append' => true]);
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
