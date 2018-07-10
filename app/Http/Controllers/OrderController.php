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

    public function nextOrder($page){
        $client = new Client();

        // Login        
        $crawler = $client->request('GET', 'http://www.eloragrosir.com/myaccount/login/');
        $form = $crawler->selectButton('wp-submit')->form();
        $crawler = $client->submit($form, array('log' => 'owner', 'pwd' => '%&P%TVT%U%'));
        $crawler = $client->request('GET', 'http://www.eloragrosir.com/wp-admin/admin.php?page=smart_report_page&cpage=' . $page);
        
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
            
            $produk_line = $order->produk_line;
            // Colors, line width and bold font
            // $this->SetFillColor(255, 0, 0);
            $header = array('Produk', 'SKU', 'Harga', 'Qty', 'Total');
            // $pdf->SetTextColor(255);
            // $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.1);
            $pdf->SetFont('', 'B',7);
            // Header
            $w = array(87,37,27,10,27);
            $num_headers = count($header);
            for($i = 0; $i < $num_headers; ++$i) {
                $pdf->Cell($w[$i], 7, ': '.$header[$i], 'TB', 0, 'L', 0);
            }
            $pdf->Ln();
            // Color and font restoration
            // $this->SetFillColor(224, 235, 255);
            $pdf->SetTextColor(0);
            $pdf->SetFont('','');
            // Data
            $fill = 0;
            foreach($produk_line as $row) {
                // $pdf->MultiCell($w[0], 5, $row->nama, 'LR', 0, 'L', $fill);
                $pdf->MultiCell($w[0], 2, $row->nama, 0, 'L', 0, 0, '', '', false);
                $pdf->MultiCell($w[1], 2, $row->sku, 0, 'L', 0, 0, '', '', false);

                // $pdf->Cell($w[1], 3, $row->sku, 0, 0, 'L', $fill);
                $pdf->Cell($w[2], 3, $row->harga, 0, 0, 'L', $fill);
                $pdf->Cell($w[3], 3, $row->qty, 0, 0, 'L', $fill);
                $pdf->Cell($w[4], 3, $row->total, 0, 0, 'L', $fill);
                $pdf->Ln();
                $pdf->Cell(array_sum($w), 3, '', 'B', 0, 'L', $fill);
                $pdf->Ln();
                // $fill=!$fill;
            }
            $pdf->Cell(array_sum($w), 0, '', 'T');
            $pdf->Ln();
            
            // biaya kirim
            $pdf->Cell($w[0], 3, 'Biaya Kirim : ', 0, 0, 'R', $fill);
            $pdf->MultiCell($w[1]+$w[2]+$w[3]+$w[4], 2, $order->biaya_kirim, 0, 'L', 0, 0, '', '', false);
            $pdf->Ln();
            $pdf->Cell(array_sum($w), 3, '', 'B', 0, 'L', $fill);
            $pdf->Ln();
            
            $pdf->Cell($w[0], 3, 'Total Belanja : ', 0, 0, 'R', $fill);
            $pdf->Cell($w[1]+$w[2]+$w[3], 3, '', 0, 0, 'R', $fill);
            $pdf->Cell($w[4], 3, $order->total, 0, 0, 'L', $fill);
            $pdf->Ln();
            $pdf->Cell(array_sum($w), 3, '', 'B', 0, 'L', $fill);
            $pdf->Ln();
            $pdf->Ln();

            // Detail Pengiriman
            // writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=0, $reseth=true, $align='', $autopadding=true)
            $pdf->writeHTMLCell(100, 10,null,null, $order->detail_pengiriman, 0, 0, 0);
            $pdf->writeHTMLCell(100, 10,null,null, $order->catatan, 0, 0, 0);

        }

        $pdf->Output('I');
        exit;
        
    }

    function printView($data){
        return view('orders.print', [
            'data' => $data
        ]);
    }

}
