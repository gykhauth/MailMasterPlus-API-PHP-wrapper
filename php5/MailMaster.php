<?php
/**
 * MailMaster REST API.
 * A MailMaster REST API egy részének példa megvalósítása. Az 
 * kód szabadon felhasználható és módosítható. Az osztály
 * jelenleg a következő API funkciókat valósítja meg:
 *   - subscribe
 *   - unsubscribe
 *   - list
 *   - update
 *   - delete
 * 
 * Használatához szükséges
 *   - legalább PHP5.2
 *   - JSON extension
 *   - cURL extension
 * 
 * Példa
 * <code>
 *    $mailmaster = new MailMaster($list_id, $form_id, $user, $passw);
 * 
 *    $fields = $db->get_row($query);
 *    $mailmaster->fields = array('mail' => 'email', 'login' => 'user_name');
 * 
 *    $row_id = $mailmaster->subscribe($fields);
 *    
 *    $mailmaster->update($row_id, array('email' => 'foo@bar.com',));
 *    $mailmaster->update('foo@bar.com', array('user_name' => 'foo',));
 *    $mailmaster->update('user_name', 'bar', array('user_name' => 'foo'));
 *    
 *    $list = $mailmaster->get();
 *    $address = $mailmaster->get($row_id);
 *    $address = $mailmaster->get('foo@bar.com');
 *    $address = $mailmaster->get('user_name', 'foo');
 * </code>
 * 
 * @package MailMaster 
 * @author Balogh Tibor <balti@aion.hu>
 * @copyright AionNext Kft. http://aion.hu
 * @license GPLv3 http://gnu.hu/gplv3.html
 */
class MailMaster {		
	public
		/**
		 * @var array A mezőnevek megfeleltetése a MailMasterben címlista neveknek.
		 * Ha a subscribe metódusnak átadott mezőnevek megegyeznek a címlista mezőnevekkel, akkor a tulajdonság üres tömb legyen.
		 * @access public
		 */
		$fields = array();

	protected
		/**
		 * @var int A legutoljára végrehajtott kérés státuskódja.
		 * @access protected
		 */
		$status = 0,
		/**
		 * @var string Az aktuálisan használt erőforrás.
		 * @access protected
		 */
		$url = '';
		
	protected
		/**
		 * @var string Az API felhasználói név.
		 * @access protected
		 */
		$user = '',
		/**
		 * @var string Az API jelszó.
		 * @access protected
		 */
		$pass = '',
		/**
		 * @var int A címlista azonosítója.
		 * @access protected
		 */
		$list_id = 0,
		/**
		 * @var string Az címlistához tartozó űrlap REST azonosítója.
		 * @access protected
		 */
		$form_id = 0;
		
	private
		/**
		 * @var string Az erőforrás címének az eleje.
		 * @access private
		 */
		$api_url = '',
		/**
		 * @var array Hibaüzenetek listája.
		 * @access private
		 */
		$errors = array(
			  0 => 'Ismeretlen hiba',
			401 => 'Sikertelen authentikáció',
			404 => 'Ismeretlen erőforrás',
			405 => 'Érvénytelen metódus',
			406 => 'Hibás paraméterek',
		);
		
	/**
	 * Konstruktor.
	 * 
	 * @param int Listaazonosító
	 * @param int Űrlapazonosító
	 * @param string Felhasználónév
	 * @param string Jelszó
	 * @return MailMaster
	 */
	function __construct($list_id, $form_id, $user, $pass) {	
		$this->list_id = (int)$list_id;
		$this->form_id = (int)$form_id;
		$this->user = $user;
		$this->pass = $pass;
		
		$this->api_url = "http://{$this->user}:{$this->pass}@restapi.emesz.com/";
	}
	
	/**
	 * Csak olvasható tulajdonságok visszaadása.
	 * 
	 * @param string Tulajdonságnév.
	 * @return mixed Tulajdonságérték.
	 * @access private
	 */
	function __get($property) {
		return @$this->$property;
	}
	
	/**
	 * A csak olvasható tulajdonság beállítva vagy sem.
	 * 
	 * @param string Tulajdonságnév.
	 * @return bool Beállítva vagy sem.
	 * @access private
	 */
	function __isset($property) {
		return isset($this->$property);
	}
	
	/**
	 * List metódus híváshoz.
	 * A list védett név, nem lehet ilyen metódust definiálni.
	 * 
	 * @param string Metódusnév.
	 * @param array Argumentumok.
	 * @return stdClass
	 * @access private
	 */
	function __call($name, $args) {
		if ($name === 'list') {
			return $this->get(@$args[0], @$args[1], @$args[2]);
		}

		trigger_error('Call to undefined method '.__CLASS__.'::'.$name, E_USER_ERROR);
	}
	
	/**
	 * Feliratkozás.
	 * Címadatok küldése a címlistára. Az 'email' mező megadása kötelező
	 * a feliratkozás megadásához. A rekord a feliratkozáskor aktív.
	 * 
	 * <code>
	 *   $mm->subscribe(array('email' => 'foo@company.com'));
	 *   $mm->subscribe(array(
	 *      'email' => 'foo@company.com',
	 *      'name' => 'Csepregi Balázs',
	 *   ));
	 * </code>
	 * @param array Név-érték párok.
	 * @return int A rekord azonosítója, hiba esetén: -1 - létező emailcím, -2 - hibás email, 0 - egyéb hiba, NULL - hibás művelet. 
	 */
	public function subscribe($fields) {
		$url = $this->api_url."subscribe/{$this->list_id}/form/{$this->form_id}";		
		return $this->send_request($url, $fields);
	}
	
	/**
	 * Leíratkozás.
	 * Az adott felhasználó inaktívvá tétele a címlistában. A metódus többféle
	 * paraméterrel is meghívható.
	 * 
	 * <code>
	 *   $mm->unsubscribe(52);
	 *   $mm->unsubscribe('foo@company.com');
	 *   $mm->unsubscribe('name', 'Csepregi Balázs');
	 * </code>
	 * 
	 * @param mixed Int - címlista címazonosító, String - emailcím vagy String - mezőnév.
	 * @param mixed Mezőnév megadásakor a mező értéke, egyébként elhagyandó.
	 * @return int 0 - sikertelen, 1 sikeres leiratkozás.
	 */
	public function unsubscribe($field, $value = NULL) {
		if ((string)(int)$field !== (string)$field || isset($value)) {
			$result = $this->get($field, $value);
			$field = (int)@$result->id;
		}
		
		$url = $this->api_url."unsubscribe/{$this->list_id}/record/$field";
		return $this->send_request($url);
	}
	
	/**
	 * Felhasználó törlése.
	 * A megadott felhasználó tényleges törlése a címlistáról. A metódus többféle
	 * paraméterrel is meghívható.
	 * 
	 * <code>
	 *   $mm->delete(52);
	 *   $mm->delete('bar@company.com');
	 *   $mm->delete('name', 'Csepregi Balázs');
	 * </code>
	 * 
	 * @param mixed Int - címlista sorazonosító, String - emailcím vagy String - mezőnév.
	 * @param mixed Mezőnév megadásakor a mező értéke, egyébként elhagyandó.
	 * @return int 0 - sikertelen, 1 - sikeres törlés.
	 */
	public function delete($field, $value = NULL) {
		if ((string)(int)$field !== (string)$field || isset($value)) {
			$result = $this->get($field, $value);
			$field = (int)@$result[0]->id;
		}
		
		$url = $this->api_url."delete/{$this->list_id}/record/$field";
		return $this->send_request($url, array(), 'DELETE');
	}
	
	/**
	 * Címlista rekordok módosítása.
	 * A metódus az azonosított rekordban módosítja az átadott mezőket.
	 * A megadott paramétereknek pontosan egy rekordot kell azonosítaniuk.
	 * 
	 * A metódus többféle paraméterrel is meghívható.
	 * <code>
	 *   $mm->update(52, array('email' => 'foo@company.com'));
	 *   $mm->update('bar@company.com', array('email' => 'foo@company.com'));
	 *   $mm->update('name', 'Csepregi Balázs', array('email' => 'foo@company.com'));
	 * </code>
	 * 
	 * @param mixed Int - címlista sorazonosító, String - emailcím vagy String - mezőnév.
	 * @param mixed Mezőnév megadásakor, a mező értéke, egyébként elhagyható.
	 * @param array Módosítandó név-érték párok.
	 * @return int A módosított rekorodk száma, hiba esetén NULL.
	 */
	public function update($field, $value, $fields = NULL) {
		if (!isset($fields)) {
			$fields = $value;
			
			if ((string)(int)$field === (string)$field) {
				//id alapján való azonosítás
				$url = $this->api_url."update/{$this->list_id}/form/{$this->form_id}/record/".(int)$field;
			} else {
				//email szerinti azonosítás
				$value = $field;
				$field = 'email';
			}
		}

		if (!isset($url)) {
			$url = $this->api_url."update/{$this->list_id}/form/{$this->form_id}/field/$field/value/$value";
		}

		if (!is_array($fields)) {
			return NULL;
		}
		
		return $this->send_request($url, $fields, 'PUT');
	}
	
	/**
	 * Cím vagy címlista lekérése.
	 * A metódus visszaadja a teljes címlistát vagy a címlista kért rekordját.
	 * A metódus alias neve: list, többféle paraméterrel is meghívható.
	 * 
	 * <code>
	 *   $mm->get();
	 *   $mm->get('foo@company.com');
	 *   $mm->get('email', 'foo@company.com');
	 *   $mm->get(12);
	 *   $mm->get('id', 12);
	 *   $mm->get('name', 'Csepregi Balázs');
	 * </code>
	 * 
	 * @param mixed Int - Címazonosító, String - emailcím vagy String - mezőnév.
	 * @param mixed Mezőnév esetén az érték, ami alapján azonosítani lehet a kért rekordot.
	 * @return stdClass A rekord jellemzői, hiba esetén NULL.
	 */
	public function get($field = NULL, $value = NULL) {
		if (!isset($value)) {
			if (!isset($field)){
				//teljes lista
				$url = $this->api_url."list/{$this->list_id}";
			} elseif ((string)(int)$field === (string)$field) {
				//id alapján való azonosítás
				$url = $this->api_url."list/{$this->list_id}/record/$field";
			} else {
				//email szerinti azonosítás
				$value = $field;
				$field = 'email';
			}
		}
		
		if (!isset($url)) {
			//név - érték pár alapján való lekérdezés
			$url = $this->api_url."list/{$this->list_id}/field/".urlencode($field).'/value/'.$value;
		}
		
		return $this->send_request($url);
	}
	
	/**
	 * Kérés küldése.
	 * Kérés küldése a MailMaster szerver felé.
	 * 
	 * @param string Kért erőforrás azonosító.
	 * @param array Küldendő adatok.
	 * @param string A kérés típusa, GET, POST, DELETE stb. NULL küldendő adat esetén mindig GET.
	 * @return mixed A válasz json dekódolt része.
	 * @access protected
	 */
	protected function send_request($url, $data = NULL, $method = 'POST') {
		$ch = curl_init($this->url = $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		if (isset($data)) {
			$request = json_encode($this->get_params($data));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		$response = curl_exec($ch);
		$this->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($this->status != 200) {
			$this->set_error();
		}

		return json_decode($response);
	}
	
	/**
	 * Hibaüzenet beállítása.
	 * 
	 * @return void
	 * @access private
	 */
	private function set_error() {
		//Hívó hely kiderítése
		$debug = debug_backtrace();
		$last = reset($debug);
		
		foreach($debug as $caller) {
			if ($last['file'] !== $caller['file']) {
				break;
			}
		}
		
		//Hibaüzenet
		$error = isset($this->errors[$this->status])? $this->errors[$this->status] : $this->errors[0];
		trigger_error('MailMaster ('.$this->status.'): '.$error.' ['.$this->url.'], hivas helye: ['.$caller['file'].' '.$caller['line'].' sor]', E_USER_WARNING);
	}
	
	/**
	 * Átadott rekordok fordítása mm címlista mezőnevekre.
	 * 
	 * @param array Lekérdezés eredményeként átadott név-érték párok.
	 * @return array MailMaster címlista név-érték párok.
	 * @access private
	 */
	private function get_params($fields) {
		if (!$this->fields) { return $fields; }
		
		$params = array();
		foreach ($fields as $field_name => $value) {
			$form_name = @$this->fields[$field_name];
			if (isset($form_name) && !$form_name){ continue; }
			if (!isset($form_name)){ $form_name = $field_name; }
			
			$params[$form_name] = $value;
		}
		return $params;
	}
}
