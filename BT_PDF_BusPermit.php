<?php

include('includes/mpdf/mpdf.php');

$margin_left = 10;
$margin_right = 10;
$margin_top = 10;
$margin_bottom = 10;
$margin_header = 10;
$margin_footer = 5;

$pdf = new mPDF('', 'A5-L', '', '', $margin_left, $margin_right, $margin_top, $margin_bottom, $margin_header, $margin_footer);

$css = '<style>
			table{
				border-collapse:collapse;
				table-layout:fixed;
			}

			
		</style>';

$pdf->WriteHTML($css);

$busID = $_POST['busid'];	


$_SESSION['Language'] = $myrow['language_id'];
include('includes/LanguageSetup.php');

$sqlApplication = "SELECT permitStartDate,
						  permitEndDate,
						  linkedpermitno,
						  busesPermitsID,
						  busespermits_typesid,
						  applicationtype,
						  startLocation
				   FROM BT_busespermits_applications
				   WHERE busespermits_applicationsid = '".$_POST['appId']."'";
				   
$resultApplication = DB_query($sqlApplication,$db);
$myrowApplication = DB_fetch_array($resultApplication);	

if($myrowApplication['busesPermitsID'] == 0){
				   
	$sqlBus = "SELECT BT_buses.routeid,BT_corridors.provinceid
			   FROM BT_buses
					INNER JOIN BT_routes ON BT_routes.routeid = BT_buses.routeid
					INNER JOIN BT_corridors ON BT_corridors.corridorid = BT_routes.corridor
			   WHERE busid ='".$_POST['busid']."'";
			   
	$resultBus = DB_query($sqlBus,$db);
	$myrowBus = DB_fetch_array($resultBus);				   

	$_POST['permitno'] =  GetNextTransNo(101, $db);
		
	$sql = "INSERT INTO BT_busespermits(provinceid,
										routeid,
										permittype,
										permitno,
										permitstartdate,
										permitenddate,
										linkedpermitno,
										userid,
										lastmodifytime,
										preuserid,
										premodifytime)
								VALUES('".$myrowBus['provinceid']."',
									   '".$myrowBus['routeid']."',
									   '".$myrowApplication['busespermits_typesid']."',
									   '".$_POST['permitno']."',
									   '".$myrowApplication['permitStartDate']."',
									   '".$myrowApplication['permitEndDate']."',
									   '".$myrowApplication['linkedpermitno']."',
									   '".$_SESSION['UserID']."',
									   '".date("Y-m-d H:m:s")."',
									   '".$_SESSION['UserID']."',
									   '".date("Y-m-d H:m:s")."')";
									   
							   
	$result = DB_query($sql,$db);
	$permitID = DB_Last_Insert_ID($db,'BT_busespermits','busespermitsid');

    $sql2 = "INSERT INTO BT_busespermits_bus(busespermitsid,
											 busid,
											 startlocation,
										  	 userid)
								VALUES('".$permitID."',
									   '".$_POST['busid']."',
									   '".$myrowApplication['startLocation']."',
									   '".$_SESSION['UserID']."')";
	$result2 = DB_query($sql2,$db);
    $permitBusID = DB_Last_Insert_ID($db,'BT_busespermits_bus','permits_busid');


	$sql3 = "INSERT INTO BT_busespermits_status(permits_busid,
												busid,
											    permitstartdate,
											    permitenddate,
											    statusupdatedate,
											    renewalstatus,
											    userid)
								VALUES('".$permitBusID."',
									   '".$_POST['busid']."',
									   '".$myrowApplication['permitStartDate']."',
									   '".$myrowApplication['permitEndDate']."',
									   '".date("Y-m-d")."',
									   '".$myrowApplication['applicationtype']."',
									   '".$_SESSION['UserID']."')";	
	$result3 = DB_query($sql3,$db);
									   
	$sqlApplication2 = "UPDATE  BT_busespermits_applications SET busesPermitsID ='".$permitID."'
                        WHERE busespermits_applicationsid = '".$_POST['appId']."'";
				   
	$resultApplication2 = DB_query($sqlApplication2,$db);								   
	
}else{
	$permitID = $myrowApplication['busesPermitsID'] ;
}	


if($_POST['permittype'] == 'Temporary Permits'){

	$sqlBusPermit = "SELECT BT_buses.busid,
							BT_buses.supplierid,
							BT_busespermits_types.permitype,
							BT_busespermits.permitno,
							BT_busespermits.issuedate,
							BT_busespermits.permitstartdate,
							BT_busespermits.permitenddate,
							BT_routes.routeno,
							BT_routes.startlocation,
							BT_routes.endlocation,
							BT_buses.noseats
						FROM BT_busespermits_bus
							 INNER JOIN BT_buses ON BT_busespermits_bus.busid=BT_buses.busid
							 INNER JOIN BT_busespermits ON BT_busespermits.busespermitsid=BT_busespermits_bus.busespermitsid
							 INNER JOIN BT_routes ON BT_routes.routeid= BT_busespermits.routeid
							 INNER JOIN BT_busespermits_types ON BT_busespermits_types.busespermits_typesid=BT_busespermits.permittype
						WHERE BT_busespermits.busespermitsid  ='".$permitID."'";
						
	$resPermit = DB_query($sqlBusPermit);
	$rowPermit = DB_fetch_array($resPermit);

	$starLocation = getRouteLocation($rowPermit['startlocation']);				
	$endLocation  = getRouteLocation($rowPermit['endlocation']);	

	$sqlOwner = "SELECT BT_busowners.suppname,BT_busowners.address1 
				 FROM BT_buses_owners 
					INNER JOIN BT_busowners ON BT_busowners.busownerid = BT_buses_owners.ownerid
				 WHERE BT_buses_owners.busid = '".$rowPermit['busid']."'";
							   
	$resOwner= DB_query($sqlOwner,$db);
	$rowOwner = DB_fetch_array($resOwner);



	$sqlFeeItems = "SELECT salesorderdetails.stkcode,
						   salesorderdetails.unitprice,
						   salesorderdetails.quantity,
						   salesorderdetails.discountpercent,
						   stockmaster.description
					FROM BT_busespermits_fees_bus
						INNER JOIN BT_busespermits_applications ON BT_busespermits_applications.busespermits_applicationsid =BT_busespermits_fees_bus.busespermits_applicationsid
						INNER JOIN salesorderdetails ON salesorderdetails.orderno =  BT_busespermits_fees_bus.orderno
						INNER JOIN stockmaster ON stockmaster.stockid =  salesorderdetails.stkcode
					WHERE BT_busespermits_applications.busespermits_applicationsid = '".$_POST['appId']."'";

	$StockResult= DB_query($sqlFeeItems,$db);

	$reason ='';

	while ($MyRowStock=DB_fetch_array($StockResult)){	
		$reason .= $MyRowStock['description'].' - '.locale_number_format($MyRowStock['unitprice'],2).'<br>&nbsp;&nbsp;';
	}

				
	$html = '
	<table width="100%"> 
		<tr style="font-size: 16px; font-weight: bold;">
			 <td align="center"  > 
				<font style="font-size: 14px; font-weight: bold;">'.$_SESSION['CompanyRecord']['coyname'].'</font><br />
				<font style="font-size: 12px;">'.$_SESSION['CompanyRecord']['regoffice1'].', '.$_SESSION['CompanyRecord']['regoffice2'].', '.$_SESSION['CompanyRecord']['regoffice3'].'</font>'.str_repeat('&nbsp;', 5).$rowPermit['permitno'].'<br /> 
				<font style="font-size: 14px; font-weight: bold;"> Temporary Bus Permit </font>
			</td>     
		</tr>
	</table>';
	$pdf->writeHTML($html, false); 
	$pdf->writeHTML('<br>', false); 


	$html = '
	<table cellpadding="5" >   
		<tr>
			<td style="font-size: 12px; font-weight: bold;" > 01. Bus Registration No </td>
			<td style="font-size: 12px;"> : '.$rowPermit['supplierid'].'</td>	
		</tr>
		<tr>
			<td  style="font-size: 12px; font-weight: bold;" >02. Route </td>
			<td style="font-size: 12px;"> : '.$rowPermit['routeno'].' '.$starLocation.'-'.$endLocation.'</td>
		</tr>
		<tr>
			<td style="font-size: 12px; font-weight: bold;" >03. Validity Period</td>
			<td style="font-size: 12px;"> : '.ConvertSQLDate($rowPermit['permitstartdate']).' - '.ConvertSQLDate($rowPermit['permitenddate']).'</td>
		</tr>
		<tr>
			<td  style="font-size: 12px; font-weight: bold;" >04. No of Seats</td>
			<td style="font-size: 12px;"> : '.$rowPermit['noseats'].'</td>
		</tr>
		<tr>
			<td  style="font-size: 12px; font-weight: bold;">05. Bus Owner</td>
			<td style="font-size: 12px; "> : '.$rowOwner['suppname'].'</td>
		</tr>
		<tr>
			<td style="font-size: 12px;font-weight: bold;">06. Address</td>
			<td style="font-size: 12px;"> : '.$rowOwner['address1'].'</td>
		</tr>
		<tr>
			<td style="font-size: 12px;font-weight: bold;" valign="top">07. Fee</td>
			<td style="font-size: 12px;" > : '.$reason.' </td>
		</tr>

	</table>';  
	$pdf->writeHTML($html, false); 



	$html = '<table border="0" cellpadding="5" width="100%" >    
				<tr width="100%" >   
					<td width="70%" style="font-size: 12px;" >Date : .......................................... </td>
					<td width="30%" style="font-size: 12px;" ><br> <br /> ...................................................<br>
																		   <b>Chairman, <br>'.$_SESSION['CompanyRecord']['coyname'].'.</b></td>								
				</tr>
						
	</table>';
		
	$pdf->writeHTML($html, false);
}

		   
	
/*$bankDetails = "<br/><br/>
				<div style='font-size: 10px;text-align: justify;'>Payments can be done through People's Bank account number 204-100-1-000-08426 or National Development Bank account number 101000613388. The account name should be SLT Campus. It's mandatory to mention your NIC or registration number in the paying-in-slip.
				</div>";
$pdf->writeHTML($bankDetails, false);*/



//$pdf->WriteHTML($tableToPDF);
//
$pdf->Output('BusPermitInvoice_'.$_GET['InvoiceNo'].'.pdf','D')

?>
