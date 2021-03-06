<?php

class DomainsController extends Shineisp_Controller_Default {
	protected $customer;
	protected $domains;
	protected $translator;
	
	/**
	 * preDispatch
	 * Starting of the module
	 * (non-PHPdoc)
	 * @see library/Zend/Controller/Shineisp_Controller_Default#preDispatch()
	 */
	
	public function preDispatch() {
		$NS = new Zend_Session_Namespace ( 'Default' );
		
		if (empty($NS->customer)) {
			$this->_helper->redirector ( 'index', 'index', 'default' );
		}
		$this->customer = $NS->customer;
		$this->domains = new Domains ();
		$this->translator = Shineisp_Registry::getInstance ()->Zend_Translate;
		
		// Set the navigation menu for the client control panel page on the left sidebar
		#$this->view->placeholder ( "left" )->append ( $string);	
		
		$this->getHelper ( 'layout' )->setLayout ( '1column' );
	}
	
	/**
	 * indexAction
	 * Redirect the user to the list action
	 * @return unknown_type
	 */
	public function indexAction() {
		$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper ( 'redirector' );
		$redirector->gotoUrl ( '/default/domains/list' );
	}
	
	public function listAction() {
		$NS = new Zend_Session_Namespace ( 'Default' );
		$form = $this->getRequest ()->getParams ();
		
		$arrSort = array ();
		$params = array ();
		
		$page = $this->getRequest ()->getParam ( 'page' );
		$sort = $this->getRequest ()->getParam ( 'sort' );
		
		if (! empty ( $sort )) {
			$arrSort [] = $this->SortingData ( $sort );
			$arrSort [] = $sort;
		} else {
			$arrSort [] = "status_id asc, DATEDIFF(expiring_date, CURRENT_DATE) asc";
		}
		
		if (! empty ( $NS->search_domains )) {
			$params = array_merge ( $params, $NS->search_domains );
			$this->view->searchactive = 1;
		} else {
			$this->view->searchactive = 0;
// 			$params ['search'] ['status_id'] ['method'] = "andWhere";
// 			$params ['search'] ['status_id'] ['criteria'] = "d.status_id <> ? AND d.status_id <> ?";
// 			$params ['search'] ['status_id'] ['value'] = array(Statuses::id('suspended', 'domains'), Statuses::id('expired', 'domains')); // Do not show the expired domain as default
		}
		
		$params ['search'][] = array ('method' => 'andWhere', 'criteria' => "(c.customer_id = ? OR c.parent_id = ?)", 'value' => array($NS->customer ['customer_id'], $NS->customer ['customer_id']) );
		
		if (isset ( $form ['domain'] )) {
			$params ['search'] [] = array ('method' => 'andWhere', 'criteria' => 'd.domain like ?', 'value' => '%' . $form ['domain'] . '%' );
		}
		
		$page = ! empty ( $page ) && is_numeric ( $page ) ? $page : 1;
		$data = $this->domains->findAll ( "d.domain_id, 
							              CONCAT(d.domain, '.', ws.tld) as domain,
							              DATE_FORMAT(d.expiring_date, '%d/%m/%Y') as endingdate, 
							              DATEDIFF(expiring_date, CURRENT_DATE) as days,
							              s.status_id as status_id, s.status as status,
							              d.autorenew as renew", $page, $NS->recordsperpage, $arrSort, $params ['search'] );
		
		$data ['tags'] = Tags::getList ( $this->customer ['customer_id'] );
		$data ['currentpage'] = $page;
		
		// Get all the status of the domains in order to fill the batch list 
		$data ['statuses'] = Statuses::getList ( 'domains', true );
		$data ['show_action_box'] = false; // This hide the status/action select box from the grid.
		

		$this->view->mex = $this->getRequest ()->getParam ( 'mex' );
		$this->view->mexstatus = $this->getRequest ()->getParam ( 'status' );
		$this->view->title = $this->translator->translate("Domain Listing");
		$this->view->description = $this->translator->translate("Here you can see all the list of your domains.");
		$this->view->domains = $data;
	}
	
	/**
	 * renewdomainAction
	 * Renew a group of domains selected
	 * @param $items
	 * @return void
	 */
	private function renewdomains($items) {
		$mex = "";
		if (is_array ( $items )) {
			try {
				$Orderid = Orders::createOrderWithMultiProducts ( $items, $this->customer ['customer_id'] );
				
				$isp   = Shineisp_Registry::get('ISP');
				$order = Orders::getAllInfo ( $Orderid, null, true );
				$link  = Fastlinks::findlinks ( $Orderid, $this->customer ['customer_id'], 'orders' );
				
				$retval = Shineisp_Commons_Utilities::getEmailTemplate ( 'new_order' );
				if ($retval) {
					$subject = $retval ['subject'];
					$subject = str_replace ( "[orderid]", sprintf ( "%03s", $Orderid ) . " - " . Shineisp_Commons_Utilities::formatDateOut ( $order [0] ['order_date'] ), $subject );
					$orderbody = $retval ['template'];
					$orderbody = str_replace ( "[fullname]", $order [0] ['Customers'] ['firstname'] . " " . $order [0] ['Customers'] ['lastname'], $orderbody );
					$orderbody = str_replace ( "[bank]", $isp->bankname . "\nc/c:" . $isp->bankaccount . "\nIBAN: " . $isp->iban . "\nBIC: " . $isp->bic, $orderbody );
					$orderbody = str_replace ( "[orderid]", $Orderid . "/" . date ( 'Y' ), $orderbody );
					$orderbody = str_replace ( "[email]", $isp->email, $orderbody );
					$orderbody = str_replace ( "[signature]", $isp->company . "\n" . $isp->email, $orderbody );
					
					if (! empty ( $link [0] )) {
						$orderbody = str_replace ( "[url]", "http://" . $_SERVER ['HTTP_HOST'] . "/index/link/id/" . $link [0] ['code'], $orderbody );
					} else {
						$orderbody = str_replace ( "[url]", "http://" . $_SERVER ['HTTP_HOST'], $orderbody );
					}
					
					if (! empty ( $order [0] ['Customers'] ['email'] )) {
						Shineisp_Commons_Utilities::SendEmail ( $isp->email, $order [0] ['Customers'] ['email'], $isp->email, $subject, $orderbody );
					}
				}
				die ( json_encode ( array ('reload' => '/orders/edit/id/' . $Orderid ) ) );
			} catch ( Exception $e ) {
				die ( json_encode ( array ('mex' => $e->getMessage () ) ) );
			}
		}
		return false;
	}
	
	/**
	 * indexAction
	 * Create the User object and get all the records.
	 * @return unknown_type
	 */
	public function resetAction() {
		$NS = new Zend_Session_Namespace ( 'Default' );
		unset ( $NS->search_domains );
		$this->_helper->redirector ( 'index', 'domains' );
	}
	
	/**
	 * editAction
	 * Get a record and populate the application form 
	 * @return unknown_type
	 */
	public function editAction() {
		$form = $this->getForm ( '/domains/process' );
		
		$id = $this->getRequest ()->getParam ( 'id' );
		
		if (! empty ( $id ) && is_numeric ( $id )) {
			
			if (! empty ( $this->customer ['customer_id'] ) && is_numeric ( $this->customer ['customer_id'] )) {
				$customer_id = $this->customer ['customer_id'];
			}
			
			// Get these fields before editing the form
			// the field autorenew has been aliased in order to create a flat array and not a multidimentional array as proposed by the Doctrine engine
			$fields = "domain_id, registrars_id, CONCAT('http://www.', d.domain, '.', ws.tld) as Domain, d.authinfocode as authinfocode, 
			p.product_id as product_id, d.domain as domainame, ws.tld as tld, d.autorenew as autorenew,  
			DATE_FORMAT(d.creation_date, '%d/%m/%Y') as Starting, (DATEDIFF(expiring_date, CURRENT_DATE)) as Days, 
			DATE_FORMAT(d.expiring_date, '%d/%m/%Y') as Termination, s.status as Status";
			
			$rs = $this->domains->getAllInfo ( $id, $customer_id, $fields, true );
			
			if (empty ( $rs )) {
				$this->_helper->redirector ( 'index', 'domains', 'default', array ('mex' => 'forbidden', 'status' => 'danger' ) );
			}
			
			$form->populate ( $rs [0] );
			
			if ($rs [0] ['autorenew']) {
				$this->view->autorenew = true;
			} else {
				$this->view->autorenew = false;
			}
			
			// Some useful values
			$this->view->name = $rs [0] ['domainame'] . "." . $rs [0] ['tld'];
			$this->view->days = $rs [0] ['Days'];
			$this->view->domain_id = $id;
			$this->view->productid = $rs [0] ['product_id'];
			
			// Show the list of the messages attached to this domain
			$this->view->messages = Messages::getbyDomainId( $id );
			$this->view->tags = Tags::findConnectionbyDomainID ( $id );
			$this->view->is_maintained = $rs [0] ['registrars_id'];
			$this->view->customerid = $customer_id;
			$this->view->authinfocode = $rs [0] ['authinfocode'];
			$this->view->id = $id;
			$this->view->orders = array('records' => domains::Orders ($id), 'edit' => array ('controller' => 'orders', 'action' => 'edit' ) );  
			$this->view->services = array('records' => domains::Services($id), 'edit' => array ('controller' => 'services', 'action' => 'edit' ) );  
			$this->view->tasks = array('records' => DomainsTasks::getTasksbyDomainID($id));  
			
			
			// Hide these fields and values inside the vertical grid object
			unset ( $rs [0] ['autorenew'] );
			unset ( $rs [0] ['authinfocode'] );
			unset ( $rs [0] ['note'] );
			unset ( $rs [0] ['domainame'] );
			unset ( $rs [0] ['tld'] );
			
			// Sent the data to the datagrid
			$this->view->datagrid = array ('records' => $rs );
			$this->view->dnsgrid = array ('records' => Dns_Zones::getZones($id, true), 'delete' => array ('controller' => 'domains', 'action' => 'deletednszone' ) );
		}
		
		$this->view->mex = $this->getRequest ()->getParam ( 'mex' );
		$this->view->mexstatus = $this->getRequest ()->getParam ( 'status' );
		
		$this->view->title = $this->translator->translate("Domain");
		$this->view->description = $this->translator->translate("Here you can edit your own domain parameters.");
		$this->view->form = $form;
		$this->_helper->viewRenderer ( 'customform' );
	}
	
	/**
	 * Delete a dns zone
	 * @return unknown_type
	 */
	public function deletednszoneAction() {
		$zoneId = $this->getRequest ()->getParam ( 'id' );
		$domain = Dns_Zones::getDomain($zoneId);

		// Check if the request comes from a real domain owner
		if(Domains::isOwner($domain[0]['domain_id'], $this->customer ['customer_id'])){
			Dns_Zones::deleteZone($zoneId);
			$this->_helper->redirector ( 'edit', 'domains', 'default', array ('id' => $domain[0]['domain_id'], 'mex' => 'The task requested has been executed successfully.', 'status' => 'success' ) );
		}else{
			$this->_helper->redirector ( 'edit', 'domains', 'default', array ('id' => $domain[0]['domain_id'], 'mex' => $this->translator->translate('You are not the domain\'s owner.'), 'status' => 'danger' ) );
		}
		
	}	
	
	/**
	 * Schedule the updating of the DNS Zones
	 */
	public function updatednszoneAction() {
		$domainId = $this->getRequest ()->getParam ( 'id' );
		if(Domains::isOwner($domainId, $this->customer ['customer_id'])){
			$domain = Domains::getName($domainId);
			DomainsTasks::AddTask($domain, 'setDomainHosts');
			$this->_helper->redirector ( 'edit', 'domains', 'default', array ('id' => $domainId, 'mex' => 'The domain task requested has been scheduled successfully. Please see the tasks tab form.', 'status' => 'success' ) );
		}else{
			$this->_helper->redirector ( 'edit', 'domains', 'default', array ('id' => $domainId, 'mex' => $this->translator->translate('You are not the domain\'s owner.'), 'status' => 'danger' ) );
		}
	}
		
	/**
	 * processAction
	 * Update the record previously selected
	 * @return unknown_type
	 */
	public function processAction() {
		$request = $this->getRequest ();
		try {
			// Check if we have a POST request
			if (! $request->isPost ()) {
				return $this->_helper->redirector ( 'index' );
			}
			
			// Get our form and validate it
			$form = $this->getForm ( '/admin/domains/process' );
			
			if (! $form->isValid ( $request->getPost () )) {
				// Invalid entries
				$this->view->form = $form;
				$this->view->title = $this->translator->translate("Domain Process");
				$this->view->description = $this->translator->translate("Check all the information posted before saving them.");
				return $this->_helper->viewRenderer ( 'customform' ); // re-render the login form
			}
			
			// Get the values posted
			$params = $form->getValues ();
			
			// Get the id 
			$id = $params['domain_id'];
			
			if (! empty ( $params ['dnsform']['target'] ) && !empty($params['dnsform'] ['zones'])) {
				Dns_Zones::addDnsZone($id, $params['dnsform'] ['subdomain'], $params['dnsform']['target'], $params['dnsform'] ['zones']);
			}
			
			// Save the message note
			if (! empty ( $params ['note'] )) {
				Messages::addMessage($params ['note'], $this->customer ['customer_id'], $id);
				$isp = Shineisp_Registry::get('ISP');
				
				$placeholder['fullname'] = $this->customer ['firstname'] . " " . $this->customer ['lastname'];
				$placeholder['domain'] = domains::getDomainName($id);
				$placeholder['message'] = $params ['note'];
				$placeholder['messagetype'] = $this->translator->translate('Domain');
			
				Messages::sendMessage ( "message_new", $this->customer ['email'], $placeholder);
				Messages::sendMessage ( "message_admin", $isp->email, $placeholder);
				
			}
			
			Domains::setAuthInfo($id, $params ['authinfocode']);
			Domains::setAutorenew($id, $params ['autorenew']);
			
			$this->_helper->redirector ( 'edit', 'domains', 'default', array ('id' => $id, 'mex' => 'The task requested has been executed successfully.', 'status' => 'success' ) );
		} catch ( Exception $e ) {
			die ( $e->getMessage () );
		}
	}
	
	/**
	 * searchProcessAction
	 * Search the record 
	 * @return unknown_type
	 */
	public function searchprocessAction() {
		$NS = new Zend_Session_Namespace ( 'Default' );
		$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper ( 'redirector' );
		$request = $this->getRequest ();
		$NS->search_domains = array ();
		$params = array ();
		$values = array ();
		$fields = "";
		
		// Check if we have a POST request
		if (! $request->isPost ()) {
			return $this->_helper->redirector ( 'index' );
		}
		
		$form = $this->getForm ( '/domains/process' );
		
		if ($form->isValid ( $request->getPost () )) {
			// Check if it is an ajax request
			if ($this->_request->isXmlHttpRequest ()) {
				$postedvars = $request->getPost ();
				if (isset ( $postedvars ['params'] )) {
					$i = 0;
					$itemselected = "";
					
					foreach ( $postedvars ['params'] as $item ) {
						if ($itemselected == $item ['name']) {
							$values [] = $item ['value'];
							$fields = $item ['name'];
							$criteria [] = $item ['name'] . " = ?";
						} else {
							$values [] = $item ['value'];
							$fields = $item ['name'];
							$criteria [] = $item ['name'] . " = ?";
							$i = 0;
						}
						$itemselected = $item ['name'];
						$i ++;
					}
					
					$params ['search'] [$i] ['method'] = "andWhere";
					if (is_array ( $criteria ) && count ( $criteria ) > 1) {
						$params ['search'] [$i] ['criteria'] = "(" . implode ( " OR ", $criteria ) . ")";
					} else {
						$params ['search'] [$i] ['criteria'] = implode ( "", $criteria );
					}
					
					$params ['search'] [$i] ['value'] = $values;
				}
				$NS->search_domains = $params;
				die ( json_encode ( array ('reload' => '/domains' ) ) );
			} else {
				$params ['search'] ['method'] = "andWhere";
				$params ['search'] ['criteria'] = "status_id = ?";
				$params ['search'] ['value'] = 4;
				$NS->search_domains = $params;
				$redirector->gotoUrl ( '/domains' );
			}
		}
		die ();
	}
	
	/**
	 * getForm
	 * Get the customized application form 
	 * @return unknown_type
	 */
	private function getForm($action) {
		$form = new Default_Form_DomainsForm ( array ('action' => $action, 'method' => 'post' ) );
		return $form;
	}
	
	/**
	 * SortingData
	 * Manage the request of sorting of the user 
	 * @return string
	 */
	private function sortingData($sort) {
		$strSort = "";
		if (! empty ( $sort )) {
			$sort = addslashes ( htmlspecialchars ( $sort ) );
			$sorts = explode ( "-", $sort );
			
			foreach ( $sorts as $sort ) {
				$sort = explode ( ",", $sort );
				$strSort .= $sort [0] . " " . $sort [1] . ",";
			}
			
			if (! empty ( $strSort )) {
				$strSort = substr ( $strSort, 0, - 1 );
			}
		}
		
		return $strSort;
	}
	
	/**
	 * recordsperpage
	 * Set the number of the records per page
	 * @return unknown_type
	 */
	public function recordsperpageAction() {
		$NS = new Zend_Session_Namespace ( 'Default' );
		$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper ( 'redirector' );
		$records = $this->getRequest ()->getParam ( 'id' );
		if (! empty ( $records ) && is_numeric ( $records )) {
			$NS->recordsperpage = $records;
		} elseif (! empty ( $records ) && $records == "all") {
			$NS->recordsperpage = 999999;
		}
		$redirector->gotoUrl ( '/domains/' );
	}
	
	/**
	 * bulkexport
	 * Custom function called by the Bulk action method
	 * @param $items
	 * @return url link
	 */
	public function bulkExport($items) {
		if (is_array ( $items )) {
			$fields = "d.domain, d.tld";
			$domains = $this->domains->get_domains ($items, $fields );
			$cvs = Shineisp_Commons_Utilities::cvsExport ( $domains );
			die ( json_encode ( array ('mex' => '<a href="/public/documents/export.csv">' . $registry->Zend_Translate->translate ( "Download" ) . '</a>' ) ) );
		}
		die ( json_encode ( array ('mex' => $this->translator->translate ( "An error occurred during the export." ) ) ) );
	}
	

	/**
	 * Show the domain profile form
	 * @return unknown_type
	 */
	public function profileAction() {
	    $request = $this->getRequest ();
	    $form = new Default_Form_DomainsProfilesForm ( array ('action' => '/domains/saveprofile/', 'method' => 'post' ) );
	    
	    $this->view->title = $this->translator->translate("Domain Owner/Assignee Profile");
	    $this->view->description = $this->translator->translate("To create a new owner/assignee fill this form. Please create your new owner/assignee here below and open a ticket with the request of domain assignement to a owner.");
	    
	    if($request->getParam('id')){
	        $profileId = $request->getParam('id');
	        if(is_numeric($profileId)){
	            $record = DomainsProfiles::getProfile($profileId, $this->customer['customer_id']);
	            if(!empty($record)){
    	            $this->view->title = $record['lastname'] . " " . $record['firstname'];
    	            $record['profile_id'] = $profileId;
    	            $form->populate($record);
	            }
	        }
	    }
	    
	    $this->view->mex = $this->getRequest ()->getParam ( 'mex' );
	    $this->view->mexstatus = $this->getRequest ()->getParam ( 'status' );
	    $this->view->form = $form;
	    
	    $this->_helper->viewRenderer ( 'profile' );
	}

	/**
	 * Show the domain profile form
	 * @return unknown_type
	 */
	public function profilesAction() {
	    $request = $this->getRequest ();
	    
	    $this->view->title = $this->translator->translate("Domain Owner/Assignee Profiles");
	    $this->view->description = $this->translator->translate("Here you can see the Owner/Assignee profile list.");
	    
	    $columns = array();
	    $columns[] = $this->translator->translate('Last name');
	    $columns[] = $this->translator->translate('First name');
	    $columns[] = $this->translator->translate('Email');
	    $columns[] = $this->translator->translate('City');
	    
	    $records = DomainsProfiles::getProfilesByCustomerId($this->customer['customer_id'], "profile_id, firstname, lastname, email, city");
	    
	    $this->view->mex = $this->getRequest ()->getParam ( 'mex' );
	    $this->view->mexstatus = $this->getRequest ()->getParam ( 'status' );
	    $this->view->profiles = array ('records' => $records, 'delete'=> array('controller' => 'domains', 'action' => 'delprofile'), 'edit' => array('controller' => 'domains', 'action' => 'profile'), 'columns' => $columns );;
	    
	    $this->_helper->viewRenderer ( 'profiles' );
	}

	/**
	 * Delete the domain profile 
	 * @return unknown_type
	 */
	public function delprofileAction() {
	    $request = $this->getRequest ();
	    
	    $this->view->title = $this->translator->translate("Domain Profiles");
	    $this->view->description = $this->translator->translate("Here you can see the domain profile list.");

	    if($request->getParam('id')){
	        $profileId = $request->getParam('id');
	        if(is_numeric($profileId)){
	            if(0 == DomainsNichandle::isUsed($profileId)){
    	            $records = DomainsProfiles::delProfile($profileId, $this->customer['customer_id']);
    	            $this->_helper->redirector ( 'profiles', 'domains', 'default', array ('mex' => 'The task requested has been executed successfully.', 'status' => 'success' ) );
	            }else{
	                $this->_helper->redirector ( 'profiles', 'domains', 'default', array ('mex' => 'You cannot delete the profile because it is connected to a domain.', 'status' => 'danger' ) );
	            }
	        }
	    }
	    
	    $this->_helper->redirector ( 'profiles', 'domains', 'default', array ('mex' => 'A problem has been occurred during the request.', 'status' => 'danger' ) );
	    
	    $this->view->mex = $this->getRequest ()->getParam ( 'mex' );
	    $this->view->mexstatus = $this->getRequest ()->getParam ( 'status' );
	    $this->_helper->viewRenderer ( 'profiles' );
	}

	/**
	 * Save the domain profile data
	 * @return unknown_type
	 */
	public function saveprofileAction() {
	    $request = $this->getRequest ();
	    
	    // Check if we have a POST request
	    if (! $request->isPost ()) {
	        return $this->_helper->redirector ( 'profile' );
	    }
	    
	    $form = new Default_Form_DomainsProfilesForm ( array ('action' => '/domains/profilesave/', 'method' => 'post' ) );
	    
	    $this->view->title = $this->translator->translate("Domain Profile");
	    $this->view->description = $this->translator->translate("Here you can create or edit the domain profile.");
	    
	    $this->view->mex = $this->getRequest ()->getParam ( 'mex' );
	    $this->view->mexstatus = $this->getRequest ()->getParam ( 'status' );
	    
	    $this->view->form = $form;
	    
	    if (! $form->isValid ( $request->getPost () )) {
	        return $this->_helper->viewRenderer ( 'profile' ); 
	    }
	    
	    // Get the values posted
	    $params = $form->getValues ();
	    
	    // add the customer id reference
	    $params['customer_id'] = $this->customer['customer_id'];
	    
	    $profileId = !empty($params['profile_id']) ? $params['profile_id'] : null;
	    
	    if(0 == DomainsNichandle::isUsed($profileId)){
	        $profileId = DomainsProfiles::saveAll($params, $profileId);
	    }else{
	        $this->_helper->redirector ( 'profile', 'domains', 'default', array ('id' => $profileId, 'mex' => 'You cannot edit the profile because it is connected to a domain.', 'status' => 'danger' ) );
	    }
	    
	    $this->_helper->redirector ( 'profile', 'domains', 'default', array ('id' => $profileId, 'mex' => 'The task requested has been executed successfully.', 'status' => 'success' ) );
	    
	    
	    $this->_helper->viewRenderer ( 'profile' );
	}
	
	/**
	 * Register
	 * Execute a Register task
	 */
	public function registerAction() {
		$domainID = $this->getRequest ()->getParam('id');
		$action = $this->getRequest ()->getParam('do');

		// Check if the request comes from the owner of the domain
		if(!Domains::isOwner($domainID, $this->customer['customer_id'])){
			$this->_helper->redirector ( 'list', 'domains', 'default', array ('mex' => 'A problem has been occurred during the request.', 'status' => 'danger' ) );	
		}
		
		// Get the domain name
		$domain = Domains::getDomainName($domainID);
		
		if(empty($domain)){
			$this->_helper->redirector ( 'list', 'domains', 'default', array ('mex' => 'A problem has been occurred during the request.', 'status' => 'danger' ) );
		}
		
		switch ($action) {
			case 'lockDomain':
				DomainsTasks::AddTask($domain, 'lockDomain');
				break;
			
			case 'unlockDomain':
				DomainsTasks::AddTask($domain, 'unlockDomain');
				break;
			
			case 'updateDomain':
				DomainsTasks::AddTask($domain, 'updateDomain');
				break;
			
			default:
				$this->_helper->redirector ( 'list', 'domains', 'default', array ('mex' => 'A problem has been occurred during the request.', 'status' => 'danger' ) );
				break;
		}
		
		$this->_helper->redirector ( 'edit', 'domains', 'default', array ('id' => $domainID, 'mex' => 'The task requested has been executed successfully.', 'status' => 'success' ) );
	}
}