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

        // $update = VebraAltoWrapper::getInstance()->vebraAlto->populateSection( $sectionId, $branch );
        // if( $update ){
        //     Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Finished import'));
        // }else{
        //     Craft::$app->getSession()->setNotice(Craft::t('vebra-alto-wrapper', 'Error importing'));
        // }

        $branchName = $branch;
        $linkModel = VebraAltoWrapper::getInstance()->vebraAlto->getLinkModel($sectionId);
        $fieldMapping = json_decode( $linkModel->fieldMapping );
        $branches = VebraAltoWrapper::getInstance()->vebraAlto->getBranch();

        foreach( $branches as $_branch ){
            if( $_branch->name == $branchName ){
                $branch = $_branch;
            }
        }

        $propertyList = VebraAltoWrapper::getInstance()->vebraAlto->connect( $branch->url . '/property' )['response']['property'];

        $allProps = [];
        foreach( $propertyList as $propertyKey => $property ){
            $this->setProgress($queue, $propertyKey / count($propertyList));

            $property = VebraAltoWrapper::getInstance()->vebraAlto->connect( $property->url )['response'];
            $property = json_decode(json_encode( $property ), TRUE);

            $allProps = array_merge( $allProps, [ $property ] );
            // die();

            $title = $property['address']['display'];
            $ref = $property['reference']['software'];

            $fields = array(
                'title' => $title,
                'reference' => $ref,
            );

            //var_dump( $property['files']['file'] );

            $value = VebraAltoWrapper::getInstance()->vebraAlto->getArrayValueByCsv( 'bullets,bullet', $property );
            //d( $value );

            foreach($fieldMapping as $craftField => $vebraField){
                //d( $vebraField );

                switch($vebraField){
                    case 'parish':
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
                VebraAltoWrapper::getInstance()->vebraAlto->saveNewEntry( 1 , $fields );
            }else{
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
