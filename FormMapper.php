<?php
/*
 * This file is part of the Form Metadata library
 *
 * (c) Cameron Manderson <camm@flintinteractive.com.au>
 *
 * For full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace FlintLabs\Bundle\FormMetadataBundle;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactory;
use FlintLabs\Bundle\FormMetadataBundle\Driver\MetadataDriverInterface;
/**
 * Obtains any metadata from the entity and adds it's configuration
 * to the form
 * TODO: Support field groups
 * @author camm (camm@flintinteractive.com.au)
 */
class FormMapper
{
    /**
     * Drivers that will be used to obtaining metadata
     * @var array
     */
    private $drivers = array();

    /**
     * @var \Symfony\Component\Form\FormFactory
     */
    protected $factory;

    /**
     * @param \Symfony\Component\Form\FormFactory $factory
     */
    public function __construct(FormFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Obtains any form metadata from the entity and adds itself to the form
     * @param null $data
     * @param string $name
     * @param array $options
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    public function createFormBuilder($data = null, $name = '', array $options = array())
    {
        if (array_key_exists('method', $options)) {
            $method = $options['method'];
            unset($options['method']);
        }

        // Build the $form
        $formBuilder = $this->factory->createNamedBuilder($name, FormType::class, $data, $options);

        if (isset($method)) {
            $formBuilder->setMethod($method);
        }

        // Read the entity meta data and add to the form
        if(empty($this->drivers)) return $formBuilder;

        // Look to the readers to find metadata
        foreach ($this->drivers as $driver) {
            $metadata = $driver->getMetadata($data);
            if(!empty($metadata)) break;
        }

        if(empty($metadata)) return $formBuilder;

        // Configure the form
        $fields = $metadata->getFields();
        foreach($fields as $field) {
            // TODO: Detect "new x()" in field value or type option for AbstractType creation
            // TODO: Detect references to "%service.id%" for service constructor dependency
            $fieldOptions = $field->options;

            if (in_array($formBuilder->getMethod(), ['POST']) && array_key_exists('onCreate', $fieldOptions)) {
                if ($fieldOptions['onCreate']) {
                    $fieldOptions = array_replace($fieldOptions, $fieldOptions['onCreate']);
                }
                else {
                    continue;
                }
            }

            elseif (in_array($formBuilder->getMethod(), ['PUT', 'PATCH']) && array_key_exists('onEdit', $fieldOptions)) {
                if ($fieldOptions['onEdit']) {
                    $fieldOptions = array_replace($fieldOptions, $fieldOptions['onEdit']);
                }
                else {
                    continue;
                }
            }


            unset($fieldOptions['onCreate']);
            unset($fieldOptions['onEdit']);

            $formBuilder->add($field->name, $field->value, $fieldOptions);
        }


        return $formBuilder;
    }

    /**
     * Add an entity metadata reader to the readers
     * @param EntityMetadataReaderInterface $reader
     * @return void
     */
    public function addDriver(MetadataDriverInterface $driver)
    {
        $this->drivers[] = $driver;
    }
}
