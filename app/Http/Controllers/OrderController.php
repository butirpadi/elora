<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Illuminate\Support\Collection;
use FPDF;

class OrderController extends Controller
{
    public function index(){
        $client = new Client();

        // Login        
        $crawler = $client->request('GET', 'http://www.eloragrosir.com/myaccount/login/');
        $form = $crawler->selectButton('wp-submit')->form();
        $crawler = $client->submit($form, array('log' => 'owner', 'pwd' => '%&P%TVT%U%'));
        $crawler = $client->request('GET', 'http://www.eloragrosir.com/wp-admin/admin.php?page=smart_report_page');
        
        $transIdArr = [];
        $nextPageUrl = '';
        $nextPageNum = '';
        $crawler->filter('#wpbody-content > div.wrap > table > tbody > tr[class!=detord]')->each(function($row)use(&$transIdArr, $crawler, $client, &$nextPageUrl, &$nextPageNum){
            $colIdx = 1;
            $transId='';
            $tanggal='';
            $total='';
            $status='';
            $row->filter('td')->each(function($col)use(&$colIdx, &$transId, &$tanggal, &$total, &$status){
                if($colIdx == 1){
                    // $col->filter('div')->each(function($colCont)use(&$status))
                    $status = $col->text();
                }
                if($colIdx == 2){
                    $transId = str_replace('Show Detail','',$col->text());
                    $transId = str_replace(' ','',$transId);
                    $transId = str_replace('|','',$transId);
                    
                }
                if($colIdx == 3){
                    $tanggal = $col->text();                    
                }
                if($colIdx == 4){
                    $total = $col->text();
                    
                }
                
                $colIdx++;
            });

            # get paging
            $page_url = "";
            $nextPageUrl = $crawler->filterXpath("//a[contains(@class, 'next')]")->attr('href');
            $nextPageNum = str_replace('/wp-admin/admin.php?page=smart_report_page&cpage=','',$nextPageUrl);
            // $last_page = $crawler->filterXpath("//div[contains(@class, 'paginet')]")->html();            
            
            # get pengiriman            
            $detail_pengiriman = '';
            $crawler->filter('div#div' . $transId )->each(function($node)use(&$detail_pengiriman){
                $detail_pengiriman = $node->html();
                $crIdx = strpos($detail_pengiriman,'<div');
                // print('Index : ' . $crIdx);
                $detail_pengiriman = substr_replace($detail_pengiriman,'',$crIdx,strlen($detail_pengiriman));                
            });
            
            $catatan = $crawler->filterXpath('//div[@id="div' . $transId . '"]/div')->html();
            // $detail_pesanan = $crawler->filterXpath('//tr[@id="hidden-' . $transId . '"]/td[2]/table[1]')->html();
            // $detail_pesanan = str_replace('<br><small>,  </small>','',$detail_pesanan);
            
            # get status and biaya pengiriman
            # get from link to invoice
            $secCrawler = $client->request('GET', 'http://www.eloragrosir.com/myaccount/order-detail/?smart=' . $transId);
            
            // Detail Pesanan/Produk
            $detail_pesanan = $secCrawler->filterXpath("//div[contains(@class, 'produk-ordered')]/table[1]")->html();
            $detail_pesanan = str_replace('<br><small>,  </small>','',$detail_pesanan);
            $detail_pesanan = str_replace('<br><small>  </small>','',$detail_pesanan);

            // make detail pesanan as json
            // print('generate json');
            // $secCrawler->filterXpath("//div[contains(@class, 'produk-ordered')]/table[1]/tbody/tr")->each(function($row){
            //     print('test <br/>');
            // });
            // $secCrawler->filter("div.produk-ordered > table > tbody > tr[class!=titletable]")->each(function($row){
            //     print('test <br/>');
            // });
            
            // echo $secCrawler->filter("div.produk-ordered > table > tr[class!=titletable]")->html();

            $tableIdx = 1;
            // $data_pesanan = [];
            $biaya_kirim = '';
            $produk_line = [];
            $secCrawler->filter("div.produk-ordered > table")->each(function($det)use(&$tableIdx, &$data_pesanan, &$biaya_kirim, &$produk_line){
                if ($tableIdx == 1){
                    // breakdown ke column
                    $det->filter('tr[class!=titletable]')->each(function($dataRow)use(&$data_pesanan, &$biaya_kirim, &$produk_line){
                        // print('Class : ' .$dataRow->attr('class') . ' ----- ');
                        if($dataRow->attr('class') != 'tottabk kirim'){
                            $dataColIdx = 1;
                            $produk = [];
                            $produk_nama = '';
                            $produk_sku = '';
                            $produk_harga = '';
                            $produk_qty = '';
                            $produk_total = '';

                            $dataRow->filter('td')->each(function($dataCol)use(&$data_pesanan, &$biaya_kirim, &$produk, &$produk_line, &$dataColIdx, &$produk_nama, &$produk_sku, &$produk_harga, &$produk_qty, &$produk_total){
                                if($dataColIdx == 1){
                                    // array_push($produk,[
                                    //     'nama' => $dataCol->text()
                                    // ]);
                                    $produk_nama = $dataCol->text();
                                }
                                if($dataColIdx == 2){
                                    // array_push($produk,[
                                    //     'sku' => $dataCol->text()
                                    // ]);
                                    $produk_sku = $dataCol->text();
                                }
                                if($dataColIdx == 3){
                                    // array_push($produk,[
                                    //     'harga' => $dataCol->text()
                                    // ]);
                                    $produk_harga = $dataCol->text();
                                }
                                if($dataColIdx == 4){
                                    // array_push($produk,[
                                    //     'jumlah' => $dataCol->text()
                                    // ]);
                                    $produk_qty = $dataCol->text();
                                }
                                if($dataColIdx == 5){
                                    // array_push($produk,[
                                    //     'total' => $dataCol->text()
                                    // ]);
                                    $produk_total = $dataCol->text();
                                }
                                $dataColIdx++;
                            });
                            array_push($produk_line, [
                                'nama' => $produk_nama,
                                'sku' => $produk_sku,
                                'harga' => $produk_harga,
                                'qty' => $produk_qty,
                                'total' => $produk_total,
                            ]);
                        }

                        // // Get Biaya Kirim
                        // $dataColIdx = 1;
                        // if($dataRow->attr('class') == 'tottabk kirim'){
                        //     // if (strpos($dataRow->filter('td')->first()->text(),'Biaya Pengiriman')){
                        //         $dataRow->filter('td')->each(function($dataCol)use(&$data_pesanan, &$biaya_kirim, &$pesanan, &$dataColIdx){
                        //             if($dataColIdx == 1){
                        //                 array_push($data_pesanan,[
                        //                     'biaya_kirim_text' => $dataCol->text()
                        //                 ]);
                        //             }
                        //             if($dataColIdx == 2){
                        //                 array_push($data_pesanan,[
                        //                     'biaya_kirim' => $dataCol->text()
                        //                 ]);
                        //             }
                        //             $dataColIdx++;
                        //         });                                
                        //     // }
                        // }

                    });

                    // array_push($data_pesanan,[
                    //     'produk_line' => $produk_line
                    // ]);

                    // Get Biaya Kirim
                    $biaya_kirim = $det->filter('tr.kirim')->first()->filter('td')->last()->text();
                }
                $tableIdx++;
            });
            // print('<br/>');
            // print('-----------------------------------------------------');
            // print('<br/>');
            
            // Status
            $status = $secCrawler->filterXpath("//div[contains(@class, 'order-content')]/p[3]/span[1]")->html();
            
            # add data to array
            array_push($transIdArr, [
                'id' => $transId,
                'status' => $status,
                'tanggal' => $tanggal,
                'total' => $total,
                'detail_pengiriman' => $detail_pengiriman,
                'detail_pesanan' => $detail_pesanan,
                'produk_line' => $produk_line,
                'catatan' => $catatan,
                'status' => $status,
                'biaya_kirim' => $biaya_kirim,
            ]);
        });

        $transIdObj = json_encode($transIdArr);

        
        return view('orders.index', [
            'orders' => json_decode($transIdObj),
            'nextPageUrl' => $nextPageUrl,
            'nextPageNum' => $nextPageNum,
        ]);

        
    }

    // public function nextOrder($page){
    //     $client = new Client();

    //     // Login        
    //     $crawler = $client->request('GET', 'http://www.eloragrosir.com/myaccount/login/');
    //     $form = $crawler->selectButton('wp-submit')->form();
    //     $crawler = $client->submit($form, array('log' => 'owner', 'pwd' => '%&P%TVT%U%'));
    //     $crawler = $client->request('GET', 'http://www.eloragrosir.com/wp-admin/admin.php?page=smart_report_page&cpage=' . $page);
        
    //     $transIdArr = [];
    //     $nextPageUrl = '';
    //     $nextPageNum = '';
    //     $crawler->filter('#wpbody-content > div.wrap > table > tbody > tr[class!=detord]')->each(function($row)use(&$transIdArr, $crawler, $client, &$nextPageUrl, &$nextPageNum){
    //         $colIdx = 1;
    //         $transId='';
    //         $tanggal='';
    //         $total='';
    //         $status='';
    //         $row->filter('td')->each(function($col)use(&$colIdx, &$transId, &$tanggal, &$total, &$status){
    //             if($colIdx == 1){
    //                 // $col->filter('div')->each(function($colCont)use(&$status))
    //                 $status = $col->text();
    //             }
    //             if($colIdx == 2){
    //                 $transId = str_replace('Show Detail','',$col->text());
    //                 $transId = str_replace(' ','',$transId);
    //                 $transId = str_replace('|','',$transId);
                    
    //             }
    //             if($colIdx == 3){
    //                 $tanggal = $col->text();                    
    //             }
    //             if($colIdx == 4){
    //                 $total = $col->text();
                    
    //             }
                
    //             $colIdx++;
    //         });

    //         # get paging
    //         $page_url = "";
    //         $nextPageUrl = $crawler->filterXpath("//a[contains(@class, 'next')]")->attr('href');
    //         $nextPageNum = str_replace('/wp-admin/admin.php?page=smart_report_page&cpage=','',$nextPageUrl);
    //         // $last_page = $crawler->filterXpath("//div[contains(@class, 'paginet')]")->html();            
            
    //         # get pengiriman            
    //         $detail_pengiriman = '';
    //         $crawler->filter('div#div' . $transId )->each(function($node)use(&$detail_pengiriman){
    //             $detail_pengiriman = $node->html();
    //             $crIdx = strpos($detail_pengiriman,'<div');
    //             // print('Index : ' . $crIdx);
    //             $detail_pengiriman = substr_replace($detail_pengiriman,'',$crIdx,strlen($detail_pengiriman));                
    //         });
            
    //         $catatan = $crawler->filterXpath('//div[@id="div' . $transId . '"]/div')->html();
            
    //         # get status and biaya pengiriman
    //         # get from link to invoice
    //         $secCrawler = $client->request('GET', 'http://www.eloragrosir.com/myaccount/order-detail/?smart=' . $transId);
            
    //         // Detail Pesanan/Produk
    //         $detail_pesanan = $secCrawler->filterXpath("//div[contains(@class, 'produk-ordered')]/table[1]")->html();
    //         $detail_pesanan = str_replace('<br><small>,  </small>','',$detail_pesanan);
    //         $detail_pesanan = str_replace('<br><small>  </small>','',$detail_pesanan);

    //         $tableIdx = 1;
    //         $data_pesanan = [];
    //         $biaya_kirim = [];
    //         $secCrawler->filter("div.produk-ordered > table")->each(function($det)use(&$tableIdx, &$data_pesanan, &$biaya_kirim){
    //             if ($tableIdx == 1){
    //                 // breakdown ke column
    //                 $det->filter('tr[class!=titletable]')->each(function($dataRow)use(&$data_pesanan, &$biaya_kirim){
    //                     // print('Class : ' .$dataRow->attr('class') . ' ----- ');
    //                     if($dataRow->attr('class') != 'tottabk kirim'){
    //                         $dataColIdx = 1;
    //                         $pesanan = [];
    //                         $dataRow->filter('td')->each(function($dataCol)use(&$data_pesanan, &$biaya_kirim, &$pesanan, &$dataColIdx){
    //                             if($dataColIdx == 1){
    //                                 array_push($pesanan,[
    //                                     'nama' => $dataCol->text()
    //                                 ]);
    //                             }
    //                             if($dataColIdx == 2){
    //                                 array_push($pesanan,[
    //                                     'sku' => $dataCol->text()
    //                                 ]);
    //                             }
    //                             if($dataColIdx == 3){
    //                                 array_push($pesanan,[
    //                                     'harga' => $dataCol->text()
    //                                 ]);
    //                             }
    //                             if($dataColIdx == 4){
    //                                 array_push($pesanan,[
    //                                     'jumlah' => $dataCol->text()
    //                                 ]);
    //                             }
    //                             if($dataColIdx == 5){
    //                                 array_push($pesanan,[
    //                                     'total' => $dataCol->text()
    //                                 ]);
    //                             }
    //                             $dataColIdx++;
    //                         });
    //                         array_push($data_pesanan, [
    //                             'produk' => $pesanan
    //                         ]);
    //                     }

    //                     // Get Biaya Kirim
    //                     $dataColIdx = 1;
    //                     if($dataRow->attr('class') == 'tottabk kirim'){
    //                         $dataRow->filter('td')->each(function($dataCol)use(&$data_pesanan, &$biaya_kirim, &$pesanan, &$dataColIdx){
    //                             if($dataColIdx == 1){
    //                                 array_push($data_pesanan,[
    //                                     'biaya_kirim_text' => $dataCol->text()
    //                                 ]);
    //                             }
    //                             if($dataColIdx == 2){
    //                                 array_push($data_pesanan,[
    //                                     'biaya_kirim' => $dataCol->text()
    //                                 ]);
    //                             }
    //                             $dataColIdx++;
    //                         });
    //                     }
    //                 });
    //             }
    //             $tableIdx++;
    //         });
            
    //         // Status
    //         $status = $secCrawler->filterXpath("//div[contains(@class, 'order-content')]/p[3]/span[1]")->html();
            
    //         # add data to array
    //         array_push($transIdArr, [
    //             'id' => $transId,
    //             'status' => $status,
    //             'tanggal' => $tanggal,
    //             'total' => $total,
    //             'detail_pengiriman' => $detail_pengiriman,
    //             'detail_pesanan' => $detail_pesanan,
    //             'data_pesanan' => $data_pesanan,
    //             'catatan' => $catatan,
    //             'status' => $status,
    //         ]);
    //     });

    //     $transIdObj = json_encode($transIdArr);

        
    //     return view('orders.index', [
    //         'orders' => json_decode($transIdObj),
    //         'nextPageUrl' => $nextPageUrl,
    //         'nextPageNum' => $nextPageNum,
    //     ]);
    // }

    public function printOrder(Request $req){
        
        // echo $req->order;
        $orders = json_decode($req->order);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $orderIdx = 1;

        foreach($orders as $order){
            $pdf->AddPage();
            // $pdf->SetFont('helvetica','',10);
            $pdf->SetFont('helvetica','B',16);
            $pdf->Cell(40,5,'Elora Grosir');
            $pdf->Cell(0,5,'INVOICE #' . $order->id,0,0,'R');
            $pdf->Ln();
            $pdf->SetFont('helvetica',null,10);
            $pdf->Cell(0,5,$order->tanggal,0,0,'R');
            $pdf->Ln(10);
            // $pdf->WriteHTML($order->detail_pengiriman);
            // $pdf->writeHTML($order->detail_pengiriman, true, false, true, false, 'J');
            
           
            // $pesanan = $order->detail_pesanan;
            // // $pesanan = str_replace('<tr class="titletable"><td>Produk</td><td>Nomor barang</td><td>Harga</td><td>Qty</td><td>Total</td></tr>','',$pesanan);
            // $pesanan = str_replace('<tr class="titletable">','',$pesanan);
            // $pesanan = str_replace("\n",'',$pesanan);
            // $pesanan = str_replace("<td>Produk</td>",'',$pesanan);
            // $pesanan = str_replace("          <td>Nomor barang</td>",'',$pesanan);
            // $pesanan = str_replace("          <td>Harga</td>",'',$pesanan);
            // $pesanan = str_replace("          <td>Qty</td>",'',$pesanan);
            // $pesanan = str_replace("          <td>Total</td>",'',$pesanan);
            // $crIdx = strpos($pesanan,'</tr>');
            // $pesanan = substr_replace($pesanan,'',$crIdx,strlen('</tr>'));
            // $pesanan = str_replace('bgcolor="#FFFFFF"','',$pesanan);
            // $pesanan = str_replace('  style="font-size: 12px;"','',$pesanan);
            // $pesanan = str_replace('class="tf" ','',$pesanan);
            // $pesanan = str_replace('data-title="Nama Produk"> ','data-title="Nama Produk">',$pesanan);

            // $fixPesanan = '<table class="table table-bordered" >';
            // $fixPesanan .= '<thead  >
            //                 <tr style="border-top:thin solid black;" >
            //                     <th style="width:20%;" >
            //                         <b>Produk</b>
            //                     </th>
            //                     <th style="width:20%;" >
            //                         <b>Nomor</b>
            //                     </th>
            //                     <th style="width:20%;" >
            //                         <b>Price</b>
            //                     </th>
            //                     <th style="width:20%;" >
            //                         <b>Qty</b>
            //                     </th>
            //                     <th style="width:20%;" >
            //                         <b>Total</b>
            //                     </th>
            //                 </tr>
            //             </thead>';
            // $fixPesanan .= '<tbody>';
            // $fixPesanan .= $pesanan;
            // $fixPesanan .= '</tbody></table>';

            // // $pdf->writeHTML($pesanan, true, false, true, false, 'J');
            // $pdf->writeHTML($fixPesanan, true, false, true, false, '');
            // // $pdf->writeHTML($this->printView($order), true, false, true, false, '');

            // $pdf->writeHTML($order->detail_pengiriman, true, false, true, false, '');
            // $pdf->writeHTML($order->catatan, true, false, true, false, '');

            // get produk with produk object
            // $data_pesanan = json_decode(json_encode($order->data_pesanan));
            // // print_r($data_pesanan);

            // echo $data_pesanan[1]->biaya_kirim . '<br/>';
            // $produk_lines = json_decode(json_encode($data_pesanan[4]->produk_line));
            // // print_r($data_pesanan[4]->produk_line);
            // foreach($produk_lines as $prd){
            //     $produk = json_decode(json_encode($prd));
            //     $produk = json_decode(json_encode($produk->produk));
            //     print_r($produk[0]->nama);
            //     print('<br>++++++++++++++++<br>');
            // }

            $produk_line = $order->produk_line;
            print_r($produk_line);

            // end of orders loop
            print($orderIdx );
            print('<br>------------------------------------------<br>');
            $orderIdx++;
        }
        // $pdf->Output('I');
        // exit;
        
        // foreach($orders as $order){
        //     $pesanan = $order->detail_pesanan;
        //     // $pesanan = str_replace('<tr class="titletable"><td>Produk</td><td>Nomor barang</td><td>Harga</td><td>Qty</td><td>Total</td></tr>','',$pesanan);
        //     $pesanan = str_replace('<tr class="titletable">','',$pesanan);
        //     $pesanan = str_replace("\n",'',$pesanan);
        //     $pesanan = str_replace("<td>Produk</td>",'',$pesanan);
        //     $pesanan = str_replace("          <td>Nomor barang</td>",'',$pesanan);
        //     $pesanan = str_replace("          <td>Harga</td>",'',$pesanan);
        //     $pesanan = str_replace("          <td>Qty</td>",'',$pesanan);
        //     $pesanan = str_replace("          <td>Total</td>",'',$pesanan);
        //     $crIdx = strpos($pesanan,'</tr>');
        //     $pesanan = substr_replace($pesanan,'',$crIdx,strlen('</tr>'));
        //     $pesanan = str_replace('bgcolor="#FFFFFF"','',$pesanan);
        //     $pesanan = str_replace('  style="font-size: 12px;"','',$pesanan);
        //     $pesanan = str_replace('class="tf" ','',$pesanan);
        //     $pesanan = str_replace('data-title="Nama Produk"> ','data-title="Nama Produk">',$pesanan);

        //     $fixPesanan = '<table class="table table-bordered" >';
        //     $fixPesanan .= '<thead>
        //                     <tr>
        //                         <th style="width:20%;" >
        //                             <b>Produk</b>
        //                         </th>
        //                         <th style="width:20%;" >
        //                             <b>Nomor</b>
        //                         </th>
        //                         <th style="width:20%;" >
        //                             <b>Price</b>
        //                         </th>
        //                         <th style="width:20%;" >
        //                             <b>Qty</b>
        //                         </th>
        //                         <th style="width:20%;" >
        //                             <b>Total</b>
        //                         </th>
        //                     </tr>
        //                 </thead>';
        //     $fixPesanan .= '<tbody>';
        //     $fixPesanan .= $pesanan;
        //     $fixPesanan .= '</tbody></table>';
        //     print_r($fixPesanan);
        // }

        // foreach($orders as $order){
        //     return $this->printView($order);
        //     break;
        // }


        // return view('orders.print', ['orders' => $orders]);
    }

    function printView($data){
        return view('orders.print', [
            'data' => $data
        ]);
    }

}
