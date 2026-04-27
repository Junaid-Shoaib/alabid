<?php


namespace App\Http\Controllers;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\HttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Exception;

class FbrInvoiceController extends Controller
{
    public function posting(Invoice $invoice, Request $request)
    {
        // Guard: prevent re-posting an already posted invoice
        if ($invoice->posting == 1 && $invoice->fbr_invoice_no != null) {
            return back()->with('sw_error', 'This invoice has already been posted to FBR.');
        }

        // Cache lock: prevent duplicate simultaneous requests
        $lockKey = 'fbr_posting_invoice_' . $invoice->id;
        $lock = cache()->lock($lockKey, 60);

        if (!$lock->get()) {
            return back()->with('sw_error', 'This invoice is already being processed. Please wait a moment and refresh.');
        }

        try {
            $apiUrl = "https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb";
            $apiKey = env('apiKey');

            // Unified payload for both Registered and Unregistered
        if($invoice->registration_type === "Registered"){
            $payload = [
                'invoiceType' => $invoice->invoice_type,
                'invoiceDate' => Carbon::parse($invoice->date_of_supply)->format('Y-m-d'),
                'sellerNTNCNIC' => env('sellerNTNCNIC'),
                'sellerBusinessName' => env('sellerBusinessName'),
                'sellerProvince' =>  env('sellerProvince'),
                'sellerAddress' =>  env('sellerAddress'),
    
                'buyerNTNCNIC' => $invoice->customer->ntn_cnic,
                'buyerBusinessName' => $invoice->customer->name,
                'buyerProvince' => $invoice->customer->province,
                'buyerAddress' => $invoice->customer->address,
                'buyerRegistrationType' => 'Registered',
                'invoiceRefNo' => $invoice->invoice_no,
                'scenarioId' => 'SN001',
                'items' => $invoice->items->map(function ($item) {
                        return [
                            'hsCode' => $item->hs_code,
                            'productDescription' => $item->product_name ?? $item->description ,
                            'rate' => round($item->sale_tax_rate) . '%',
                            'uoM' => $item->uom,
                            'quantity' => $item->quantity,
                            'totalValues' => $item->total, 
                            'valueSalesExcludingST' => $item->value_of_goods,
                            'fixedNotifiedValueOrRetailPrice' => 0,
                            'salesTaxApplicable' => $item->amount_of_saleTax,
                            'salesTaxWithheldAtSource' => $item->sale_tax_withheld ?? 0,
                            'extraTax' => $item->extra_tax ?? 0,
                            'furtherTax' => $item->further_tax ?? 0,
                            'sroScheduleNo' => '',
                            'fedPayable' => 0,
                            'discount' => 0,
                            'saleType' => $item->sale_type ?? 'Goods at standard rate (default)',
                            'sroItemSerialNo' => ''
                        ];
                    })->toArray()
            ];
        }
        if($invoice->registration_type === "Unregistered"){
            $payload = [
                'invoiceType' => $invoice->invoice_type,
                'invoiceDate' => Carbon::parse($invoice->date_of_supply)->format('Y-m-d'),
                'sellerNTNCNIC' => env('sellerNTNCNIC'),
                'sellerBusinessName' => env('sellerBusinessName'),
                'sellerProvince' =>  env('sellerProvince'),
                'sellerAddress' =>  env('sellerAddress'),
    
                'buyerNTNCNIC' => $invoice->customer->ntn_cnic,
                'buyerBusinessName' => $invoice->customer->name,
                'buyerProvince' => $invoice->customer->province,
                'buyerAddress' => $invoice->customer->address,
                'buyerRegistrationType' => 'Unregistered',
                'invoiceRefNo' => $invoice->invoice_no,
                'scenarioId' => 'SN002',
                'items' => $invoice->items->map(function ($item) {
                        return [
                            'hsCode' => $item->hs_code,
                            'productDescription' => $item->product_name ?? $item->description ,
                            'rate' => round($item->sale_tax_rate) . '%',
                            'uoM' => $item->uom,
                            'quantity' => $item->quantity,
                            'totalValues' => $item->total, 
                            'valueSalesExcludingST' => $item->value_of_goods,
                            'fixedNotifiedValueOrRetailPrice' => 0,
                            'salesTaxApplicable' => $item->amount_of_saleTax,
                            'salesTaxWithheldAtSource' => $item->sale_tax_withheld ?? 0,
                            'extraTax' => $item->extra_tax ?? 0,
                            'furtherTax' => $item->further_tax ?? 0,
                            'sroScheduleNo' => '',
                            'fedPayable' => 0,
                            'discount' => 0,
                            'saleType' => $item->sale_type ?? 'Goods at standard rate (default)',
                            'sroItemSerialNo' => ''
                        ];
                    })->toArray()
            ];
        }
       

            $client = HttpClient::create();

            $response = $client->request('POST', $apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ],
                'json'    => $payload,
                'timeout' => 28,
            ]);

            $responseJson = $response->toArray(false);
            $statusCode   = $response->getStatusCode();

            if ($statusCode == 200 && isset($responseJson['validationResponse'])) {
                $validation = $responseJson['validationResponse'];

                // Case 1: Success
                if ($validation['statusCode'] == "00") {
                    $invoice->fbr_invoice_no = $responseJson['invoiceNumber'];
                    $invoice->response       = serialize($responseJson);
                    $invoice->posting        = 1;
                    $invoice->save();

                    return back()->with('sw_success', 'Invoice Posted Successfully');
                }

                // Case 2: Validation Error
                if ($validation['statusCode'] == "01" && !empty($validation['error'])) {
                    return back()->with('sw_error', $validation['error']);
                }

                // Case 3: Item-level errors
                if (isset($validation['invoiceStatuses'])) {
                    foreach ($validation['invoiceStatuses'] as $status) {
                        if ($status['statusCode'] == "001") {
                            return back()->with('sw_error', 'Validation: ' . ($status['error'] ?? 'Item validation failed'));
                        }
                    }
                }

                return back()->with('sw_error', 'FBR Validation Failed with unknown error.');

            } elseif ($statusCode == 401) {
                return back()->with('sw_error', 'FBR Authentication Failed (Invalid API Key).');
            } else {
                return back()->with('sw_error', 'Something Went Wrong. Status Code: ' . $statusCode);
            }

        } catch (TimeoutException $e) {
            return back()->with('sw_error', 'The FBR server is currently not responding. Please try again in a few minutes.');
        } catch (\Exception $e) {
            return back()->with('sw_error', 'Connection Error: ' . $e->getMessage());
        } finally {
            // Always release lock whether success or failure
            $lock->release();
        }
    }


    // public function posting(Invoice $invoice, Request $request){
        
    //     $apiUrl = "https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata"; 
    //     $apiKey = env('apiKey'); 
        
    //     if($invoice->registration_type === "Registered"){
    //         $payload = [
    //             'invoiceType' => $invoice->invoice_type,
    //             'invoiceDate' => Carbon::parse($invoice->date_of_supply)->format('Y-m-d'),
    //             'sellerNTNCNIC' => env('sellerNTNCNIC'),
    //             'sellerBusinessName' => env('sellerBusinessName'),
    //             'sellerProvince' =>  env('sellerProvince'),
    //             'sellerAddress' =>  env('sellerAddress'),
    
    //             'buyerNTNCNIC' => $invoice->customer->ntn_cnic,
    //             'buyerBusinessName' => $invoice->customer->name,
    //             'buyerProvince' => $invoice->customer->province,
    //             'buyerAddress' => $invoice->customer->address,
    //             'buyerRegistrationType' => 'Registered',
    //             'invoiceRefNo' => $invoice->invoice_no,
    //             'scenarioId' => 'SN001',
    //             'items' => $invoice->items->map(function ($item) {
    //                     return [
    //                         'hsCode' => $item->hs_code,
    //                         'productDescription' => $item->product_name ?? $item->description ,
    //                         'rate' => round($item->sale_tax_rate) . '%',
    //                         'uoM' => $item->uom,
    //                         'quantity' => $item->quantity,
    //                         'totalValues' => $item->total, 
    //                         'valueSalesExcludingST' => $item->value_of_goods,
    //                         'fixedNotifiedValueOrRetailPrice' => 0,
    //                         'salesTaxApplicable' => $item->amount_of_saleTax,
    //                         'salesTaxWithheldAtSource' => $item->sale_tax_withheld ?? 0,
    //                         'extraTax' => $item->extra_tax ?? 0,
    //                         'furtherTax' => $item->further_tax ?? 0,
    //                         'sroScheduleNo' => '',
    //                         'fedPayable' => 0,
    //                         'discount' => 0,
    //                         'saleType' => $item->sale_type ?? 'Goods at standard rate (default)',
    //                         'sroItemSerialNo' => ''
    //                     ];
    //                 })->toArray()
    //         ];
    //     }
    //     if($invoice->registration_type === "Unregistered"){
    //         $payload = [
    //             'invoiceType' => $invoice->invoice_type,
    //             'invoiceDate' => Carbon::parse($invoice->date_of_supply)->format('Y-m-d'),
    //             'sellerNTNCNIC' => env('sellerNTNCNIC'),
    //             'sellerBusinessName' => env('sellerBusinessName'),
    //             'sellerProvince' =>  env('sellerProvince'),
    //             'sellerAddress' =>  env('sellerAddress'),
    
    //             'buyerNTNCNIC' => $invoice->customer->ntn_cnic,
    //             'buyerBusinessName' => $invoice->customer->name,
    //             'buyerProvince' => $invoice->customer->province,
    //             'buyerAddress' => $invoice->customer->address,
    //             'buyerRegistrationType' => 'Unregistered',
    //             'invoiceRefNo' => $invoice->invoice_no,
    //             'scenarioId' => 'SN002',
    //             'items' => $invoice->items->map(function ($item) {
    //                     return [
    //                         'hsCode' => $item->hs_code,
    //                         'productDescription' => $item->product_name ?? $item->description ,
    //                         'rate' => round($item->sale_tax_rate) . '%',
    //                         'uoM' => $item->uom,
    //                         'quantity' => $item->quantity,
    //                         'totalValues' => $item->total, 
    //                         'valueSalesExcludingST' => $item->value_of_goods,
    //                         'fixedNotifiedValueOrRetailPrice' => 0,
    //                         'salesTaxApplicable' => $item->amount_of_saleTax,
    //                         'salesTaxWithheldAtSource' => $item->sale_tax_withheld ?? 0,
    //                         'extraTax' => $item->extra_tax ?? 0,
    //                         'furtherTax' => $item->further_tax ?? 0,
    //                         'sroScheduleNo' => '',
    //                         'fedPayable' => 0,
    //                         'discount' => 0,
    //                         'saleType' => $item->sale_type ?? 'Goods at standard rate (default)',
    //                         'sroItemSerialNo' => ''
    //                     ];
    //                 })->toArray()
    //         ];
    //     }
       

    //     // $client = HttpClient::create();
    //     // try {
    //         // $response = $client->request('POST', $apiUrl, [
    //         //     'headers' => [
    //         //         'Content-Type' => 'application/json',
    //         //         'Authorization' => "Bearer {$apiKey}",
    //         //     ],
    //         //     'json' => $payload, 
    //         //     'timeout' => 120,
    //         // ]);
    //         //     $statusCode = $response->getStatusCode();
    //         //     $responseBody = $response->toArray(false); 
    //         //     $responseJson = json_decode($responseBody, true);
    //         //     if($statusCode == 200 && $responseJson != null) {
    //         //         if(isset($responseJson['validationResponse'])){
    //         //             if($responseJson['validationResponse']['statusCode'] == "00"){
    //         //                 $fbrInvNo = $responseJson['invoiceNumber']; 
    //         //                 $invoice->fbr_invoice_no = $fbrInvNo;
    //         //                 $invoice->response = serialize($responseJson);
    //         //                 $invoice->posting = 1;
    //         //                 $invoice->save();
    //         //                 return back()->with('success', "Invoice Posted Successfully");
    //         //             }elseif(!isset($responseJson['validationResponse']['invoiceStatuses']) && $responseJson['validationResponse']['statusCode'] == "01"){
    //         //                 return back()->with('sw_error',$responseJson['validationResponse']['error']);
    //         //             }else{
    //         //         foreach($responseJson['validationResponse']['invoiceStatuses'] as $key => $validateResp){
    //         //             if($validateResp['statusCode'] == "001"){
    //         //                         return back()->with('sw_error',$responseJson['validationResponse']['invoiceStatuses'][$key]['error']);	
    //         //             }
    //         //         }
    //         //             }
    //         //         }else{
    //         //             return back()->with('sw_error','Validation Response Failed!');
    //         //         }
    //         //     }elseif($statusCode == 401){
    //         //         return back()->with('sw_error',$responseJson['validationResponse']['error']);
    //         //     }else{
    //         //         return back()->with('sw_error','Something Went Wrong');
    //         //     }
    //         //         return $responseJson;
    //         // }
    //         //  catch (\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
    //         //     return back()->with('sw_error', 'The FBR server is currently not responding. Please try again in a few minutes.');
    //         // } catch (\Exception $e) {
    //         //     return back()->with('sw_error', 'Connection Error: ' . $e->getMessage());
    //         // }



    //     $client = HttpClient::create();

    //     try {
    //         $response = $client->request('POST', $apiUrl, [
    //             'headers' => [
    //                 'Content-Type' => 'application/json',
    //                 'Authorization' => "Bearer {$apiKey}",
    //             ],
    //             'json' => $payload, 
    //             'timeout' => 28, // Nginx timeout (usually 30s) se kam rakhein taake Catch block chale
    //         ]);

    // // Symfony toArray(false) automatically json_decode kar deta hai aur errors throw nahi karta
    //         $responseJson = $response->toArray(false);
    //         $statusCode = $response->getStatusCode();

    //         if ($statusCode == 200 && isset($responseJson['validationResponse'])) {
    //             $validation = $responseJson['validationResponse'];

    //             // Case 1: Success
    //             if ($validation['statusCode'] == "00") {
    //                     $fbrInvNo = $responseJson['invoiceNumber']; 
    //                     $invoice->fbr_invoice_no = $fbrInvNo;
    //                     $invoice->response = serialize($responseJson);
    //                     $invoice->posting = 1;
    //                     $invoice->save();
    //                 // $invoice->update([
    //                 //     'fbr_invoice_no' => $responseJson['invoiceNumber'],
    //                 //     'response'       => serialize($responseJson),
    //                 //     'posting'        => 1,
    //                 // ]);
    //                 return back()->with('sw_success', "Invoice Posted Successfully");
    //             }

    //             // Case 2: Validation Error (01)
    //             if ($validation['statusCode'] == "01" && isset($validation['error']) && $validation['error'] != "") {
    //                 return back()->with('sw_error', $validation['error']);
    //             }

    //             // Case 3: Specific Item Status Errors
    //             if (isset($validation['invoiceStatuses'])) {
    //                 foreach ($validation['invoiceStatuses'] as $status) {
    //                     if ($status['statusCode'] == "001") {
    //                         return back()->with('sw_error', 'Validation: '.$status['error'] ?? 'Item validation failed');
    //                     }
    //                 }
    //             }
                
    //             return back()->with('sw_error', 'FBR Validation Failed with unknown error.');

    //         } else if($statusCode == 401) {
                
    //             return back()->with('sw_error', 'FBR Authentication Failed (Invalid API Key).');
    //         } else {
                
    //             return back()->with('sw_error', 'Something Went Wrong. Status Code: ' . $statusCode);
    //         }

    //     } catch (TimeoutException $e) {
            
    //         return back()->with('sw_error', 'The FBR server is currently not responding. Please try again in a few minutes.');
    //     } catch (\Exception $e) {
               
    //         return back()->with('sw_error', 'Connection Error: ' . $e->getMessage());
    //     }
        
    // }
}

