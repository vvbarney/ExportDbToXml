<?php
/**
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2014 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class ExportDbToXml extends Module
{
	protected $config_form = false;

	//Filter: tables started with pattern
	protected $exported_tables = array (
			"ps_configuration",
			"ps_tax",
			"ps_stock",
			"ps_manufacturer",
			"ps_category",
			"ps_product",
			"ps_feature",
			"ps_image",
			"ps_attribute",
			"ps_specific_price",
		);
	
	public function __construct()
	{
		$this->name = 'ExportDbToXml';
		$this->tab = 'export';
		$this->version = '1.1.3';
		$this->author = 'Exalogic s.r.o., Vladimir Iricek';
		$this->need_instance = 0;

		/**
		 * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
		 */
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Export database to XML');
		$this->description = $this->l('Export prestashop database to XML');
	}

	/**
	 * Don't forget to create update methods if needed:
	 * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
	 */
	public function install()
	{
		Configuration::updateValue('EXPORTDBTOXML_LIVE_MODE', false);

		return parent::install();
	}

	public function uninstall()
	{
		Configuration::deleteByName('EXPORTDBTOXML_LIVE_MODE');

		return parent::uninstall();
	}

	/**
	 * Load the configuration form
	 */
	public function getContent()
	{
		/**
		 * If values have been submitted in the form, process.
		 */
		$this->html = $this->renderForm();
		$this->_postProcess();
		
		return $this->html;
	}

	/**
	 * Create the form that will be displayed in the configuration of your module.
	 */
	protected function renderForm()
	{
		$helper = new HelperForm();

		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$helper->module = $this;
		$helper->default_form_language = $this->context->language->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitExportDbToXmlModule';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
			.'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		return $helper->generateForm(array($this->getConfigForm()));
	}

	/**
	 * Create the structure of your form.
	 */
	protected function getConfigForm()
	{
		return array(
			'form' => array(
				'legend' => array(
				'title' => $this->l('Settings'),
				'icon' => 'icon-cogs',
				),
				'submit' => array(
					'title' => $this->l('Export database to XML'),
					'class' => 'btn btn-default pull-right',
					'name' => 'submitExport',
				),
			),
		);
	}

	/**
	 * Save form data.
	 */
	protected function _postProcess()
	{
		if (Tools::isSubmit('submitExport'))
		{
			//-- start buffering
			//ob_start();

			$url_path = _PS_BASE_URL_.__PS_BASE_URI__;
			$file_name = _DB_PREFIX_._DB_NAME_.'.xml';
			$file_pathname = dirname(__FILE__).'\\export\\'.$file_name;
			$this->_export($file_pathname, $url_path);
			
			/*	
			$this->html .= $this->displayConfirmation( 
				'<br />'.
				'<a href="'.$url_path.'/modules/ExportDbToXml/export/'.$file_name.'" download="'.$file_name.'">
					Download file: "'.$file_name.'"
				</a>'.
				'<br />'
			);
			*/
			
			$file_size = filesize($file_pathname); 

		
			header('Content-Description: File Transfer');
			header('Content-Type: text/xml; charset=UTF-8');
			header('Cache-Control: no-store, no-cache');
			header('Content-Disposition: attachment; filename="'.$file_name.'"');
			header('Cache-Control: must-revalidate');
			header('Expires: 0');
			header('Pragma: public');
			header('Content-Length: '.$file_size);
			
			//clear output buffer
			ob_end_clean();

			//flush();
			readfile($file_pathname);
			exit;
			//flush();
			
			//ob_start();
		}	
		return true;
		
	}
	
	protected function _is_exported_table($table_name)
	{
		foreach($this->exported_tables as $beg_name)
		{
			if(strpos($table_name, $beg_name) === 0) return true;
		}
		return false;
	}
	
	protected function _table_xml($fd, $table_name_orq)
	{
		$table_name = preg_replace('/^'._DB_PREFIX_.'/', 'ps_', $table_name_orq);

		if ($this->_is_exported_table($table_name) === false) return "";
		
		//echo "Mem= " . (memory_get_usage(false) / 1024 ) . "/";			

		$xml = " <".$table_name.">\r\n";
		fwrite($fd, $xml);

		$query  = 'SELECT * FROM '.$table_name_orq;
//		if($result = Db::getInstance()->executeS($query, true, 0))
		if($result = Db::getInstance()->query($query))
		{
			//foreach($result as $row)
			while ($row = Db::getInstance()->nextRow($result))
			{
				$xml = "  <row>\r\n";
				foreach($row as $key => $value)
				{
					if (preg_match("/[^A-Za-z0-9]/", $value))
					{
						//neplatné znaky, vlož do CDATA
						$xml .= "    <".$key."><![CDATA[".$value."]]></".$key.">\r\n";
					}
					else
					{
						$xml .= "    <".$key.">".$value."</".$key.">\r\n";
					}
				}
				$xml .= "  </row>\r\n";
				fwrite($fd, $xml);

			}
			//echo (memory_get_usage(false) / 1024 ) . "/";
			unset($result);
		}

		$xml = " </".$table_name.">\r\n";
		fwrite($fd, $xml);

		
		//echo (memory_get_usage(false) / 1024) . " : ";			
		//return $xml;
		return true;
	}

	protected function _export($file_name, $url_path)
	{
		
		$query  = 'SHOW TABLES FROM `'._DB_NAME_.'` LIKE "'._DB_PREFIX_.'%"';
		
		if($result = Db::getInstance()->executeS($query))
	
		
		if($fd = @fopen($file_name, 'w'))
		{
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
			$xml .= '<Data href="'.$url_path.'">'."\r\n";
			fwrite($fd, $xml);
			
			foreach($result as $row)
			{				
				$table_name = $row['Tables_in_'._DB_NAME_.' ('._DB_PREFIX_.'%)'];
				$this->_table_xml($fd, $table_name);			
			}

			$xml = "</Data>\r\n";
			fwrite($fd, $xml);
			
			//$this->html .= $this->displayConfirmation('Export was succesfull. To file: '.$file_name);
			fclose($fd);
		}
		else
			$this->html .= $this->displayError('Error: cannot write: '.$file_name);
	}
}
