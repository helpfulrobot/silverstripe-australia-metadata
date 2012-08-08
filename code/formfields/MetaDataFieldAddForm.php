<?php

class MetaDataFieldAddForm implements GridField_URLHandler {
    
    /**
	 * @var String
	 */
	protected $template = 'GridFieldDetailForm';

    public function getURLHandlers($gridField) {
        return array(
            'add' => 'addfield'
        );
    }

	public function __construct($name = 'AddForm') {
		$this->name = $name;
	}

    public function addfield($gridField, $request) {
		$controller = $gridField->getForm()->Controller();
		if(is_numeric($request->param('ID'))) {
			$record = $gridField->getList()->byId($request->param("ID"));
		} else {
			$record = Object::create($gridField->getModelClass());	
		}

		$handler = Object::create('MetaDataFieldAddForm_ItemRequest', $gridField, $this, $record, $controller, $this->name);
		$handler->setTemplate($this->template);

		return $handler->handleRequest($request, DataModel::inst());
	}


    public function getValidator(){
    	return new RequiredFields(array());
    }

    public function getItemEditFormCallback(){
    	return false;
    }


 }

 class MetaDataFieldAddForm_ItemRequest extends GridFieldDetailForm_ItemRequest {
    protected $gridField;
    protected $component;
    protected $record;

    public function __construct($gridField, $component, $record, $popupController, $popupFormName) {
        parent::__construct($gridField, $component, $record, $popupController, $popupFormName);
    }

    public function Link($action = null) {
		return Controller::join_links($this->gridField->Link('add'), $action);
	}

	function edit($request) {
		$controller = $this->getToplevelController();
		$form = $this->AddForm($this->gridField, $request);

		$return = $this->customise(array(
			'Backlink' => $controller->hasMethod('Backlink') ? $controller->Backlink() : $controller->Link(),
			'ItemEditForm' => $form,
		))->renderWith($this->template);

		if($request->isAjax()) {
			return $return;	
		} else {
			// If not requested by ajax, we need to render it within the controller context+template
			return $controller->customise(array(
				// TODO CMS coupling
				'Content' => $return,
			));	
		}
	}


    /**
	 * @return Form
	 */
	public function AddForm() {
		
		$fields = new FieldList(
			new LiteralField('SelectFieldType', '<p><strong>Please select a field type to add:</strong></p>'),
			new DropdownField('ClassName', '', $this->getFieldTypes(), null, null, true)
		);

		if($schemaID = (int)$this->request->param('ID')) {
			$fields->push(new HiddenField('SchemaID', '', $schemaID)); 
		}

		$actions = new FieldList(FormAction::create('doAddField', 'Go')
			->setUseButtonTag(true)->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'add'));
		
		$form = new Form($this, 'AddForm', $fields, $actions);

		$toplevelController = $this->getToplevelController();

		$form->setTemplate('LeftAndMain_EditForm');
		$form->addExtraClass('cms-content cms-edit-form center ss-tabset stacked');
		$form->setAttribute('data-pjax-fragment', 'CurrentForm Content');
		if($form->Fields()->hasTabset()) $form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');

		$parents = $this->popupController->Breadcrumbs(false)->items;
		$form->Backlink = array_pop($parents)->Link; 

		return $form;
		
	}


	public function doAddField($data, $form){
		$allowedClasses = ClassInfo::subclassesFor('MetadataField');
		$class 			= isset($data['ClassName']) ? $data['ClassName'] : null;

		if($class && in_array($class, $allowedClasses)){
			$field = new $class;
			$form->saveInto($field);
			$field->write();
			return Controller::curr()->redirect(Controller::join_links($this->gridField->Link(), 'item',  $field->ID, 'edit'));
		}else{
			//$form->sessionMessage('Could not add Field, not a valid class', 'bad');
			return Controller::curr()->redirectBack();
		}
	
		
	}


	/**
	 * @return array
	 */
	protected function getFieldTypes() {
		$classes = ClassInfo::subclassesFor('MetadataField');
		$result  = array();

		array_shift($classes);
		foreach ($classes as $class) {
			$result[$class] = singleton($class)->getFieldTitle();
		}

		return $result;
	}

}

