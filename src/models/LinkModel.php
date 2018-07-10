<?php
/**
 * Vebra Alto Wrapper plugin for Craft CMS 3.x
 *
 * Integration with the estate agency software vebraalto.com
 *
 * @link      https://github.com/Jegard
 * @copyright Copyright (c) 2018 Luca Jegard
 */

namespace jegardvebra\vebraaltowrapper\models;

use jegardvebra\vebraaltowrapper\VebraAltoWrapper;

use Craft;
use craft\base\Model;

class LinkModel extends Model
{
    // Public Properties
    // =========================================================================
    /**
     * @var int|null ID
     */
    public $id;
    /**
     * @var int|null Entry ID
     */
    public $sectionId;

    public $branch;

    public $fieldMapping;
}