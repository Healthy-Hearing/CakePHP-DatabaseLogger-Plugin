<?php
class LogsController extends DatabaseLoggerAppController {

	var $name = 'Logs';
	var $helpers = array('Time','Icing.Csv');
	var $paginate = array(
		'order' => 'Log.id DESC',
		'fields' => array(
			'Log.created',
			'Log.type',
			'Log.message',
			'Log.id'
		)
	);

	function admin_index($filter = null) {
		if(!empty($this->data['Log']['filter'])){
			$filter = $this->data['Log']['filter'];
		}
		list($filter, $running_last_search) = $this->handleLastSearch($filter, 'Log');
		$conditions = array_merge(
			$this->Log->search($this->request->params['named']),
			$this->Log->textSearch($filter)
		);
		$this->set('logs',$this->paginate($conditions));
		$this->set('types', $this->Log->getTypes());
		$this->set('filter', $filter);
		$this->set('running_last_search', $running_last_search);
	}
	
	function admin_export($filter = null){
		$this->layout = 'csv';
		if(!empty($this->data)){
			$filter = $this->data['Log']['filter'];
		}
		if($this->RequestHandler->ext != 'csv'){
			$this->redirect(array('action' => 'export', 'ext' => 'csv', $filter));
		}
		$this->dataToNamed();
		$params = isset($this->request->params['named']['search']) ? $this->request->params['named']['search'] : array();
		$conditions = array_merge(
			$this->Log->search($params),
			$this->Log->textSearch($filter)
		);
		$options = array(
			'contain' => array(),
			'conditions' => $conditions,
			'recursive' => -1,
		);
		$count = $this->Log->find('count', $options);
		// We run into memory errors if we try to download a file that is too large
		if ($count <= 3000) {
			// Small file. Download immediately.
			$this->set('filename','export_logs.csv');
			$this->set('data', $this->Log->export($options));
		} else {
			// Large file. Dispatch shell.
			App::uses('Queue','Queue.Lib');
			$email = $this->Auth->user('email');
			$exportParams = array(
				'email' => $email,
				'options' => $options);
			$cmd = "util export_logs ".json_encode($exportParams);
			if (Queue::add($cmd, 'shell')) {
				$this->goodFlash('Large file export. Results will be emailed.');
			} else {
				$this->badFlash('Unable to add to queue: '.$cmd);
			}
			return $this->redirect(array('action' => 'index'));
		}
	}
	
	function admin_view($id = null) {
		if (!$id) {
			$this->Session->setFlash(__('Invalid log'));
			$this->redirect(array('action' => 'index'));
		}
		$this->set('log', $this->Log->read(null, $id));
	}

	function admin_delete($id = null) {
		if (!$id) {
			$this->Session->setFlash(__('Invalid id for log'));
			$this->redirect(array('action'=>'index'));
		}
		if ($this->Log->delete($id)) {
			$this->Session->setFlash(__('Log deleted'));
			$this->redirect(array('action'=>'index'));
		}
		$this->Session->setFlash(__('Log was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
