<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class BHD extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('search');
		$this->load->library('errorlog');
		$this->load->helper('my_helper');
	}
	/* FIRST API - Varify the invoice number in fedena and bookstore*/
	public function verifyInvoice()
	{
		$inNum  = $this->input->get('Num_Referencia');
		//search the invoice number in colered LMS Server
		$invoiceDetails=$this->_searchFedena($inNum);
		$invoiceDetails1=$this->_searchBookstore($inNum);
		$encodedInvoice = array();
		$invoice = array();
		if($invoiceDetails['Codigo_Respuesta'] == '100' && $invoiceDetails1['Codigo_Respuesta'] == '100' ){
				//if found in both databases
				$invoice['Codigo_Respuesta'] = '104';
				$invoice['Descripci�n_Respuesta'] = 'Invoice Not Valid';
				$message = 'Dear Admin<br/>The invoice number - '.$inNum.' exists in both databases. Please check.<br/><br/>Thanks,<br/>Banco Popular.';
				$subject = 'Duplicate Invoice Found';
				$this->_sendEmail($subject, $message);
				// write message to the log file
				$this->errorlog->lwrite('The invoice number - '.$inNum.' exists in both databases bookstore and Fedena.');

			}elseif($invoiceDetails['Codigo_Respuesta'] == '102' && $invoiceDetails1['Codigo_Respuesta'] == '102' ){
				//if not found in any databases
				$invoice['Codigo_Respuesta'] = '104';
				$invoice['Descripci�n_Respuesta'] = 'Invoice Not Found';
				$message = 'Dear Admin<br/>The invoice number - '.$inNum.' does not found in any database. Please check.<br/><br/>Thanks,<br/>Banco Popular.';
				$subject = 'Invoice Not Found';
				$this->_sendEmail($subject, $message);

				// write message to the log file
				$this->errorlog->lwrite('The invoice number - '.$inNum.' does not found in any database bookstore and Fedena.');

			}elseif($invoiceDetails['Codigo_Respuesta'] == '100' && $invoiceDetails1['Codigo_Respuesta'] != '100'){
				//if found in fedena but not in bookstore
				$invoice = $invoiceDetails;
			}elseif($invoiceDetails['Codigo_Respuesta'] != '100' && $invoiceDetails1['Codigo_Respuesta'] == '100'){
				//if found in bookstore but not in fedena
				$invoice = $invoiceDetails1;
			}elseif($invoiceDetails['Codigo_Respuesta'] == '103' && $invoiceDetails1['Codigo_Respuesta'] != '100'){
				//if found in colered LS Server but no due amount exist
				$invoice['Codigo_Respuesta'] = '104';
				$invoice['Descripci�n_Respuesta'] = 'Invoice Not Valid';

				// write message to the log file
				$this->errorlog->lwrite('The invoice number - '.$inNum.' exist in our record but there is no due amount against this invoice.');

			}
		foreach($invoice as $key => $value){
			$encodedInvoice[$this->_mb_convert($key)] = $value;
		}
		//converts array into xml
		xml_viewpage($encodedInvoice);
	}
	/* Search the invoice number in Fedena. If found return the details otherwise error*/
	public function _searchFedena($inNum)
	{

		$studentDetails = $this->search->getStudent($inNum);
		if($studentDetails)
		{
			$StudentID = $studentDetails[0]['id'];
			//Call to model function to get the fee details against invoice.
			$feeDetails = $this->search->getFedenaInvoiceDetails($StudentID);
			$invoice=array();
			if($feeDetails)
			{
				$chkstudent = $this->search->checkStudent($inNum);
				if($chkstudent)
				{
					$this->search->updateStudent($studentDetails,$inNum);
				}
				else{
					$this->search->insertStudent($studentDetails);
				}
				$count = sizeof($feeDetails);
				$balance = '';
				foreach($feeDetails as $fees)
				{
					$balance = $balance + $fees['balance'];
				}
				$due_blnc = $balance - $feeDetails[$count-1]['balance'];
				$app = FEDENA_APP_ID;
				$prevDetails = $this->search->checkInvoice($inNum);
				if($prevDetails)
					$this->search->updateInvoice($inNum, $app, $prevDetails[0]['id']);
				else
					$this->search->insertInvoice($inNum, $app);
				$invoice = array(
				'Num_Referencia' => $inNum,
				'Descripci�n_Transacci�n' => 'Fee Submission',
				'Moneda' => 'USD',
				'Monto' => '0.00',
				'Total_Pagar' => $balance,
				'Nombre_Cliente' => $feeDetails[0]['first_name'].' '.$feeDetails[0]['middle_name'].' '.$feeDetails[0]['last_name'],
				'RNC_Cedula' => $StudentID,
				'ITBIS' => '',
				'Codigo_Respuesta' => '100',
				'Otros' => $due_blnc,
				'Descripci�n_Respuesta' => 'Invoice Found'
				);
			}else{
					$invoice = array(
					'Codigo_Respuesta' => '103'
					);
				}
		}else{
			$invoice = array(
				'Codigo_Respuesta' => '102'
				);
		}
		return $invoice;
	}
	/* Search the invoice number in Bookstore. If found return the details otherwise error*/
	public function _searchBookstore($inNum)
	{
	    $invoice=array();
		$bank_status = BHD_STATUS;
		//Call to model function to get the order details against invoice.
		$orderDetails = $this->search->getBookstoreInvoiceDetails($inNum,$bank_status);
		if($orderDetails && sizeof($orderDetails) == 1)
		{
			$customerDetails = $this->search->getCustomer($orderDetails[0]['id_customer']);
			$chkcustomer = $this->search->checkCustomer($orderDetails[0]['id_customer']);
			if($chkcustomer)
			{
				$this->search->updateCustomer($customerDetails,$orderDetails[0]['id_customer']);
			}
			else
			{
				$this->search->insertCustomer($customerDetails);
			}
			$app = BOOKSTORE_APP_ID;
			$this->search->insertInvoice($inNum,$app);
			$invoiceDetails = '';
			$invoice = array(
				'Num_Referencia' => $inNum,
				'Descripci�n_Transacci�n' => 'Books Order Payment',
				'Moneda' => 'USD',
				'Monto' => '0.00',
				'Total_Pagar' => $orderDetails[0]['total_paid'],
				'Nombre_Cliente' => $orderDetails[0]['firstname'].''.$orderDetails[0]['lastname'],
				'RNC_Cedula' => $orderDetails[0]['id_customer'],
				'ITBIS' => '',
				'Codigo_Respuesta' => '100',
				'Descripci�n_Respuesta' => 'Invoice Found'
			);

		}else{
			$invoice = array(
				'Codigo_Respuesta' => '102'
			);
		}
	return $invoice;
	}
	/*Second API - To update the transcation detail into the fedena,boookstore and lms system*/
	public function applyPayment()
	{
		//get the parameters from url
		$inNum = $this->input->get('Num_Referencia');
		$descRef = $this->input->get('Description');
		$amount = $this->input->get('Total_Pagar');
		$paymentType = $this->input->get('Tipo_Pago');
		$canal    = $this->input->get('Canal');
		//serach invoice in lms database
		$apps = $this->search->searchInvoice($inNum);
		$invoice = '';
		if($apps[0]['app_id'] == FEDENA_APP_ID) {
			//if found in fedena, update the fedena database
			$invoice = $this->_callFedena($inNum, $descRef, $amount, $paymentType,$canal);
		}elseif($apps[0]['app_id'] == BOOKSTORE_APP_ID){
			//if found in bookstore, update the bookstore database
			$invoice = $this->_callBookstore($inNum, $descRef, $amount, $paymentType,$canal);
		}else{
			$invoice['Codigo_Respuesta'] = '104';
			$invoice['Descripci�n_Respuesta'] = 'Invoice Not Found';

			// write message to the log file
			$this->errorlog->lwrite('The invoice number - '.$inNum.' does not exist in our record.');
		}
		foreach($invoice as $key => $value){
			$encodedInvoice[$this->_mb_convert($key)] = $value;	
		}
		//converts array into xml
		xml_viewpage($encodedInvoice);
	}
	/*THIRD API - Validating the transactions*/
	public function reconciliation()
	{
		    //get the parameters from url
			$caseId    = $this->input->get('IdCaja');
		    $origin_Guid = $this->input->get('GuidOrigen');
			$transactions=array("IDTransaccion"=>"123456","FechaTransaccion"=>"2014-06-23 15:30:48.000000","cantidad"=>"1000","Referencia"=>"185344");
			$txn_id = $transactions['IDTransaccion'];
			$txn_date = $transactions['FechaTransaccion'];
			$txn_amt = $transactions['cantidad'];
			$txn_ref = $transactions['Referencia'];
			$txnDetails = $this->search->checkTransactionExist($txn_id);
			/*if($txnDetails['0']['app_id']=='2') {
			   $book_store_app_id=$txnDetails['0']['app_id'];
			   $book_store_invoice_num=$txnDetails['0']['invoice_number'];
			   $this->search->updateLmsCaseGuidId($book_store_app_id,$book_store_invoice_num,$caseId,$origin_Guid);
			}*/
			//if transaction validates, return the response otherwise error
			if($txnDetails)
			{
				$inv_no = $txnDetails['0']['invoice_number'];
				$amt = $txnDetails['0']['amount'];
				if($inv_no == $txn_ref && $amt == $txn_amt){
					$invoiceDetails = array(
					'CodRespuesta' => '100',
					'DescRespuesta' => 'Transaction validated successfully',
					'TransaccionesProcesadas'=>'Yes'
					);
				} else {
					$invoiceDetails = array(
					'CodRespuesta' => '105',
					'DescRespuesta' => 'Transaction not validated successfully',
					'TransaccionesProcesadas'=>'No'
					);

					// write message to the log file
					$this->errorlog->lwrite('Transaction not validated successfully');

				}
			}else{
					$invoiceDetails = array(
					'CodRespuesta' => '105',
					'DescRespuesta' => 'Transaction not validated successfully',
					'TransaccionesProcesadas'=>'No'
					);

					// write message to the log file
					$this->errorlog->lwrite('Transaction not validated successfully');

			}

		xml_viewpage($invoiceDetails);
	}
	/*FORTH API - To cancel the transaction and updating in LMS Server*/
	public function reverse()
	{
		//get the parameters from url
		$caseId = $this->input->get('IdCaja');
		$origin_Guid = $this->input->get('GuidOrigen');
		$cancelGuid = $this->input->get('GuidDetalleAnular');
		$type    = $this->input->get('Tipo');
		$reason    = $this->input->get('Motivo');
		$order_detail=$this->search->getPaymentDetail($cancelGuid);
		if($order_detail){
		   $invoice_num=$order_detail[0]['invoice_number'];
	       $id=$order_detail[0]['id'];
		   $update_date = date("Y-m-d H:i:s");
		   $this->search->updateOrderDetail($invoice_num,$update_date);
		   //update lms cancel status
		   $this->search->updateLmsCancelStatus($cancelGuid,$type,$reason,$invoice_num,$id,$caseId,$origin_Guid);
		   $invoiceDetails = array(
					'CodRespuesta' => '100',
					'DescRespuesta' => 'Cancellation successfully done.',
					'GuidOrigen'=>$origin_Guid,
					'DetalleAnulacion'=>'ZZZZZZZZZZZZZ'
					);

		}else{
		   $invoiceDetails = array(
					'CodRespuesta' => '101',
					'DescRespuesta' => 'Transaction cancellation process has been failed'
					);

					// write message to the log file
					$this->errorlog->lwrite('Transaction cancellation process has been failed');
		}
		//converts array into xml
		xml_viewpage($invoiceDetails);
	}

	/*update fee transaction details in fedena as well as LMS*/
    public function _callFedena($inNum, $descRef, $amount, $paymentType,$canal)
	{
		$originalAmount = $amount;
		$studentDetails = $this->search->getStudent($inNum);
		if($studentDetails)
		{
			$StudentID = $studentDetails[0]['id'];
			$feeDetails = $this->search->getFedenaInvoiceDetails($StudentID);
			if($feeDetails)
			{
				$totalBlnc = '';
				foreach($feeDetails as $fees){
					$totalBlnc = $totalBlnc + $fees['balance'];
				}
				if($amount > $totalBlnc) {
					$updateDetails = array(
						'Codigo_Respuesta' => '105',
						'Descripci�n_Respuesta' => 'Amount is greater than invoice amount',
					);

					// write message to the log file
					$this->errorlog->lwrite('Amount is greater than invoice amount');

				}else{
					foreach($feeDetails as $fees)
					{
						if($amount <= $fees['balance'])
						{
							$finance_id = $fees['id'];
							if($amount == $fees['balance'])
							{
								$isPaid = '1';
								$title = 'Receipt No. F'.$finance_id;
							}else{
								$isPaid = '0';
								$title = 'Receipt No. (partial) F'.$finance_id;
							}
							$balance = $fees['balance'];
							$due_amt = round($balance - $amount,2);
							$school_id = $fees['school_id'];
							$receipts = $this->search->getMaxReceiptNo('FinanceFee');
							$receipt_no = $receipts['0']['receipt_no'];
							$receipt_no = $receipt_no + 1;
							$FeeCollectionID = $fees['fee_collection_id'];
							$this->search->updateFedenaFeeDetailsByBHD($StudentID, $FeeCollectionID, $descRef, $amount, $paymentType, $inNum,$canal, $isPaid, $due_amt, $title, $finance_id, $school_id, $receipt_no);
							break;
						}
						else{
							$isPaid = '1';
							$balance = $fees['balance'];
							$pending_amt = round($amount - $balance,2);
							$due_amt = '0.00';
							$amount = $balance;
							$finance_id = $fees['id'];
							$title = 'Receipt No. F'.$finance_id;
							$school_id = $fees['school_id'];
							$receipts = $this->search->getMaxReceiptNo('FinanceFee');
							$receipt_no = $receipts['0']['receipt_no'];
							$receipt_no = $receipt_no + 1;
							$FeeCollectionID = $fees['fee_collection_id'];
							$this->search->updateFedenaFeeDetailsByBHD($StudentID, $FeeCollectionID, $descRef, $amount, $paymentType, $inNum,$canal, $isPaid, $due_amt, $title, $finance_id, $school_id, $receipt_no);
							$amount = $pending_amt;
						}
					}
					$app = FEDENA_APP_ID;
					//get lms transaction id
					$lms_txn_id = $this->search->getMaxLMSTxnId();
					if($lms_txn_id)
						$lms_txn_id = $lms_txn_id[0]['lms_txn_id'] + 1;
					else
						$lms_txn_id = DEFAULT_LMS_TXN_ID;
					$paymentDate = date("Y-m-d H:i:s");
					$bank_id = BHD_BANK_ID;
					if($this->search->updateLMS($inNum, $app, $descRef, $lms_txn_id, $originalAmount, $paymentDate, $StudentID, '', $paymentType, $canal, $bank_id))
					{
						$updateDetails = array(
									'Codigo_Respuesta' => '100',
									'Descripci�n_Respuesta' => 'Fee Details Updated',
									'Monto' => 0.00,
									'Num_Referencia' => $inNum,
									'Descripci�n_Referencia' => $lms_txn_id,
								     );
					}else{
						$updateDetails = array(
									'Codigo_Respuesta' => '106',
									'Descripci�n_Respuesta' => 'There is some problem in fee updation',
									);

						// write message to the log file
						$this->errorlog->lwrite('There is some problem in fee updation');

					}
				}
			}else{
				$updateDetails = array(
					'Codigo_Respuesta' => '103',
					'Descripci�n_Respuesta' => 'No pending fees',
					);
			}
		}else{
				$updateDetails = array(
					'Codigo_Respuesta' => '102',
					'Descripci�n_Respuesta' => 'No student found',
					);
		}
		return $updateDetails;
	}
	/*Update order transaction details in Bookstore as well as LMS*/
	public function _callBookstore($inNum, $descRef, $amount, $paymentType,$canal)
	{
		$bank_status = BHD_STATUS;
		$orderDetails = $this->search->getBookstoreInvoiceDetails($inNum,$bank_status);
		if($orderDetails){
		if($amount > $orderDetails[0]['total_paid']) {
			$updateDetails = array(
					'Codigo_Respuesta' => '105',
					'Descripci�n_Respuesta' => 'Amount is greater than invoice amount',
					);
		} else {
			$paymentDate = date("Y-m-d H:i:s");
			$paymentType = 'BHD- '.$paymentType;
			if($this->search->updateBookstoreOrderDetails($inNum, $descRef, $amount, $paymentDate, $paymentType))
			{
				$app = BOOKSTORE_APP_ID;
				$customers = $this->search->getCustomerId($inNum);
				$customer_id = $customers['0']['id_customer'];
				//get lms transaction id
				$lms_txn_id = $this->search->getMaxLMSTxnId();
				if($lms_txn_id)
						$lms_txn_id = $lms_txn_id[0]['lms_txn_id'] + 1;
					else
						$lms_txn_id = DEFAULT_LMS_TXN_ID;
				$bank_id = BHD_BANK_ID;
				$this->search->updateLMS($inNum, $app, $descRef, $lms_txn_id, $amount, $paymentDate, '', $customer_id, $paymentType, $canal, $bank_id);
				$updateDetails = array(
			     'Codigo_Respuesta' => '100',
			     'Descripci�n_Respuesta' => 'Order Details Updated',
			     'Num_Referencia' => $inNum,
				 'Descripci�n_Referencia' => $lms_txn_id,
			     'Monto' =>0.00
			     );

			}else{
				$updateDetails = array(
				'Codigo_Respuesta' => '106',
				'Descripci�n_Respuesta' => 'Order Details Not Updated',
				);

			}
		}
		}else{
			$updateDetails = array(
				'Codigo_Respuesta' => '104',
				'Descripci�n_Respuesta' => 'Invoice Not Valid',
				);
		}
		return $updateDetails;
	}

	/*Convert the special characters into utf-8*/
	public function _mb_convert($str){
       return mb_convert_encoding(trim($str),"utf-8","iso-8859-1");
    }
	/*Send the mail*/
	public function _sendEmail($subject, $message)
	{
		$to = TO_EMAIL;
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		// More headers
		$headers .= "From:FROM_NAME <FROM_EMAIL>" . "\r\n";
		mail($to,$subject,$message,$headers);
	}
}
