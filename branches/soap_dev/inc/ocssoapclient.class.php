<?php

class PluginOcsinventoryngOcsSoapClient extends PluginOcsinventoryngOcsClient {
	/**
	 * @var SoapClient
	 */
	private $soapClient;

	public function __construct($id, $url, $user, $pass) {
		parent::__construct($id);
		
		$options = array(
			'location' => "$url/ocsinterface",
			'uri' => "$url/Apache/Ocsinventory/Interface",
			'login' => $user,
			'password' => $pass,
			'trace' => true,
			'soap_version' => SOAP_1_1,
			'exceptions' => 0
		);
		
		$this->soapClient = new SoapClient(null, $options);
	}

	/**
	 * @see PluginOcsinventoryngOcsClient::checkConnection()
	 */
	public function checkConnection() {
		return !is_soap_fault($this->soapClient->ocs_config_V2('LOGLEVEL'));
	}
	
	public function searchComputers($field, $value) {
		$xml = $this->callSoap('search_computers_V1', array($field, $value));

		$computerObjs = simplexml_load_string($xml);
		
		$computers = array();
		foreach ($computerObjs as $obj) {
			$computers[] = array(
				'ID' => (int) $obj->DATABASEID,
				'CHECKSUM' => (int) $obj->CHECKSUM,
				'DEVICEID' => (string) $obj->DEVICEID,
				'LASTCOME' => (string) $obj->LASTCOME,
				'LASTDATE' => (string) $obj->LASTDATE,
				'NAME' => (string) $obj->NAME,
				'TAG' => (string) $obj->TAG
			);
		}
		
		return $computers;
	}

	/**
	 * @see PluginOcsinventoryngOcsClient::getComputers()
	 */
	public function getComputers($options) {
		$offset = $originalOffset = isset($options['OFFSET']) ? (int) $options['OFFSET'] : 0;
		$maxRecords = isset($options['MAX_RECORDS']) ? (int) $options['MAX_RECORDS'] : null;
		$originalEnd = isset($options['MAX_RECORDS']) ? $originalOffset + $maxRecords : null;

		$computers = array();
		
		do {
			$options['OFFSET'] = $offset;
			if (!is_null($maxRecords)) {
				$options['MAX_RECORDS'] = $maxRecords;
			}
			
			$xml = $this->callSoap('get_computers_V2', new PluginOcsinventoryngOcsSoapRequest($options));
			
			$computerObjs = simplexml_load_string($xml);
			
			foreach ($computerObjs as $obj) {
				$meta = $obj->META;
				
				$computer = array(
					'META' => array(
						'ID' => (int) $meta->DATABASEID,
						'CHECKSUM' => (int) $meta->CHECKSUM,
						'DEVICEID' => (string) $meta->DEVICEID,
						'LASTCOME' => (string) $meta->LASTCOME,
						'LASTDATE' => (string) $meta->LASTDATE,
						'NAME' => (string) $meta->NAME,
						'TAG' => (string) $meta->TAG
					)
				);
				
				foreach ($obj->INVENTORY->children() as $sectionName => $sectionObj) {
					$computer[$sectionName] = array();
					foreach ($sectionObj as $key => $val) {
						$computer[$sectionName][$key] = (string) $val;
					}
				}
				
				$computers []= $computer;
			}
			
			$totalCount = (int) $computerObjs['TOTAL_COUNT'];
			$maxRecords = (int) $computerObjs['MAX_RECORDS'];
			$offset += $maxRecords;
			
			// We can't load more records than there is in ocs
			$end = (is_null($orignalEnd) or $originalEnd > $totalCount) ? $totalCount : $originalEnd;
			
			if ($offset + $maxRecords > $end) {
				$maxRecords = $end - $offset;
			}
		} while ($options['OFFSET'] + $computerObjs['MAX_RECORDS'] < $end);
		
		return array(
			'TOTAL_COUNT' => $totalCount,
			'COMPUTERS' => $computers
		);
	}
	
	public function getComputerSections($ids, $checksum = self::CHECKSUM_ALL, $wanted = self::WANTED_ALL) {
		$xml = $this->callSoap('get_computer_sections_V1', array($ids, $checksum, $wanted));
		
		$computerObjs = simplexml_load_string($xml);
		
		$computers = array();
		foreach ($computerObjs as $obj) {
			$id = (int) $obj['ID'];
			$computer = array();
			
			foreach ($obj as $sectionName => $sectionObj) {
				$computer[$sectionName] = array();
				foreach ($sectionObj as $key => $val) {
					$computer[$sectionName][$key] = (string) $val;
				}
			}
			
			$computers[$id] = $computer;
		}
		
		return $computers;
	}

	public function getAccountInfo($id) {}

	/**
	 * @see PluginOcsinventoryngOcsClient::getConfig()
	 */
	public function getConfig($key) {
		$xml = $this->callSoap('ocs_config_V2', $key);
		if (!is_soap_fault($xml)) {
			$configObj = simplexml_load_string($xml);
			$config = array(
				'IVALUE' => (int) $configObj->IVALUE,
				'TVALUE' => (string) $configObj->TVALUE
			);
			return $config;
		}
		return false;
	}

	/**
	 * @see PluginOcsinventoryngOcsClient::setConfig()
	 */
	public function setConfig($key, $ivalue, $tvalue) {
		$this->callSoap('ocs_config_V2', array(
			$key,
			$ivalue,
			$tvalue
		));
	}
	
	public function getDeletedComputers() {
		$xml = $this->callSoap('get_deleted_computers_V1', new PluginOcsinventoryngOcsSoapRequest(array()));
		$deletedObjs = simplexml_load_string($xml);
		$res = array();

		foreach ($deletedObjs as $obj) {
			$res[(string) $obj['DELETED']] = (string) $obj['EQUIVALENT'];
		}
		
		return $res;
	}

	public function removeDeletedComputers($deleted, $equiv = null) {
		if ($equiv) {
			$this->callSoap('remove_deleted_computer_V1', array($deleted, $equiv));
		} else {
			$this->callSoap('remove_deleted_computer_V1', array($deleted));
		}
	}

	public function getCategorie($table, $condition = 1, $sort) {}

	public function getUnique($columns, $table, $conditions, $sort) {}

	public function setChecksum($checksum, $id) {
		$this->callSoap('reset_checksum_V1', array($checksum, $id));
	}
	
	public function getChecksum($id) {
		$xml = $this->callSoap('get_checksum_V1', $id);
		return (int) simplexml_load_string($xml);
	}

	public function getAccountInfoColumns() {}
	
	// /**
	// * @see PluginOcsinventoryngOcsClient::getComputers()
	// */
	// public function getComputers($options = array()) {
	// // TODO don't use simplexml
	// // TODO paginate
	// // Get options
	// $defaultOptions = array(
	// 'offset' => 0,
	// 'asking_for' => 'META',
	// 'checksum' => self::CHECKSUM_ALL,
	// 'wanted' => self::WANTED_ALL,
	// );
	
	// $options = array_merge($defaultOptions, $options);
	
	// $xml = $this->callSoap('get_computers_V1', new PluginOcsinventoryngOcsSoapRequest(array(
	// 'ENGINE' => 'FIRST',
	// 'OFFSET' => 0,
	// 'ASKING_FOR' => 'META',
	// 'CHECKSUM' => $options['checksum'],
	// 'WANTED' => $options['wanted'],
	// )));
	
	// $computerObjs = simplexml_load_string($xml);
	
	// $computers = array();
	// foreach ($computerObjs as $obj) {
	// $computers []= array(
	// 'ID' => (string) $obj->DATABASEID,
	// 'CHECKSUM' => (string) $obj->CHECKSUM,
	// 'DEVICEID' => (string) $obj->DEVICEID,
	// 'LASTCOME' => (string) $obj->LASTCOME,
	// 'LASTDATE' => (string) $obj->LASTDATE,
	// 'NAME' => (string) $obj->NAME,
	// 'TAG' => (string) $obj->TAG,
	// );
	// }
	
	// return $computers;
	// }
	
	// /**
	// * @see PluginOcsinventoryngOcsClient::getDicoSoftElement()
	// */
	// public function getDicoSoftElement($word) {
	// return $this->callSoap('get_dico_soft_element_V1', $word);
	// }
	
	// /**
	// * @see PluginOcsinventoryngOcsClient::getHistory()
	// */
	// public function getHistory($offset, $count) {
	// return $this->callSoap('get_history_V1', array($offset, $count));
	// }
	
	// /**
	// * @see PluginOcsinventoryngOcsClient::clearHistory()
	// */
	// public function clearHistory($offset, $count) {
	// return $this->callSoap('clear_history_V1', array($offset, $count));
	// }
	
	// /**
	// * @see PluginOcsinventoryngOcsClient::resetChecksum()
	// */
	// public function resetChecksum($checksum, $ids) {
	// $params = array_merge(array($checksum), $ids);
	// return $this->callSoap('reset_checksum_V1', $params);
	// }
	
	// /**
	// * @return SoapClient
	// */
	// public function getSoapClient() {
	// return $this->soapClient;
	// }
	
	// /**
	// * @param SoapClient $soapClient
	// */
	// public function setSoapClient($soapClient) {
	// $this->soapClient = $soapClient;
	// }
	
	/**
	 *
	 * @param string $method        	
	 * @param mixed $request        	
	 * @return mixed
	 */
	private function callSoap($method, $request) {
		if ($request instanceof PluginOcsinventoryngOcsSoapRequest) {
			$res = $this->soapClient->$method($request->toXml());
		} else if (is_array($request)) {
			$res = call_user_func_array(array(
				$this->soapClient,
				$method
			), $request);
		} else {
			$res = $this->soapClient->$method($request);
		}
		
		if (is_array($res)) {
			$res = implode('', $res);
		}
		
		return is_string($res) ? trim($res) : $res;
	}
}

?>