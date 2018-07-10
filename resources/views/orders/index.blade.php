@extends('layouts.master')

@section('content')
<!-- Content Header (Page header) -->
<section class="content-header">
      <h1>
        Orders
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Orders</a></li>
        <!-- <li><a href="#">Examples</a></li> -->
        <!-- <li class="active">Blank page</li> -->
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">

      <!-- Default box -->
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Pesanan Baru</h3>

          <div class="box-tools pull-right">
            <button id="btn-print" type="button" class="btn btn-sm btn-primary hide" title="Print">
                Print
            </button>
            @if($nextPageNum > 1)
            <a class="btn btn-sm btn-success" href="order/next/{{$nextPageNum-1}}" ><i class="fa fa-arrow-left" ></i></a>
            @endif
            <a class="btn btn-sm btn-success" href="order/next/{{$nextPageNum}}" ><i class="fa fa-arrow-right" ></i></a>
          </div>
        </div>
        <div class="box-body no-padding">
            <table class="table table-bordered table-condensed table-hover" >
                <thead>
                    <tr>
                        <th class="text-center" style="width:50px;" ><input type="checkbox" id="ck-all" /></th>
                        <th class="text-center" >ID Pesanan</th>
                        <th class="text-center" >Tanggal</th>
                        <th class="text-right" >Total</th>
                        <th class="text-center" >Status</th>
                        <th class="text-center" ></th>
                    </tr>
                </thead>            
                <tbody>
                    @foreach($orders as $ord)
                    <tr id="ord-{{$ord->id}}" data-json="{{json_encode($ord)}}" >
                        <td class="text-center" ><input type="checkbox" class="ck-row" /></td>
                        <td class="text-center" >
                            {{$ord->id}}
                        </td>
                        <td class="text-center" >
                            {{$ord->tanggal}}
                        </td>
                        <td class="text-right" >
                            {{$ord->total}}
                        </td>
                        <td class="text-center" >
                            <i>{{$ord->status}}</i>
                        </td>
                        <td class="text-center" >
                            <a data-toggle="modal" data-target="#modal-{{$ord->id}}" class="btn btn-xs btn-success btn-show-detail" data-id="{{$ord->id}}" ><i class="fa fa-eye" ></i></a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            <!-- <a class="btn btn-primary" id="btn-move" >Move</a> -->
        </div>
        <!-- /.box-footer-->
    </div>
    <!-- /.box -->
    
</section>
<!-- /.content -->

    <div id="modal-panel" >
        @foreach($orders as $ord)
            <!-- Modal -->
            <div class="modal fade" id="modal-{{$ord->id}}">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <h4 class="modal-title">Transaksi #{{$ord->id}}</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row" >
                                <div class="col-xs-12" >
                                    <b>Detail Pesanan</b>
                                    <table class="table table-bordered table-condensed no-padding table-produk-ordered" >
                                        <thead>
                                            <tr>
                                                <th>Produk</th>
                                                <th>Nomor Barang</th>
                                                <th>Harga</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        {!! $ord->detail_pesanan !!}
                                    </table>
                                </div>
                                <div class="col-xs-6" >
                                    {!! $ord->detail_pengiriman !!}
                                </div>
                                <div class="col-xs-6" >
                                    <b>Status : </b>  <i>{!! $ord->status !!}</i><br/>
                                    {!! $ord->catatan !!}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                <!-- /.modal-content -->
                </div>
                <!-- /.modal-dialog -->
            </div>
        @endforeach
    </div>

    <form class="hide"  name="send_order" method="POST" action="order/print-order" target="_blank">
        {{ csrf_field() }}
        <input name="order" />
        <button type="submit" class="btn btn-primary" >Submit</button
    </form>
@append

@section('scripts')
<script>
    $(document).ready(function () {
        $('#ck-all').change(function(){
            $('.ck-row').prop('checked',$(this).is(':checked'));
            togglePrintButton();
        });

        $('.ck-row').change(function(){
            togglePrintButton();
        });

        function togglePrintButton(){
            if($('input.ck-row:checked').length > 0){
                $('#btn-print').removeClass('hide');
                $('#btn-print').fadeIn(250);
            }else{
                $('#btn-print').fadeOut(250);
            }
        }

        function moveModal(){
            alert('moving');
            $('div.modal').appendTo('#modal-panel');
        }
        $('#btn-move').click(function(){
            moveModal();
        });
        
        $('.titletable').remove();
        $('table.table-produk-ordered tbody').children('tr').each(function(){
            $(this).removeAttr('style');
        });

        // $('tr.tottabk:first').children('td:first').removeAttr('colspan');
        // $('tr.tottabk:first').children('td:first').next().attr('colspan',4);

        $('#btn-print').click(function(){
            // alert('test');
            var orders = [];

            $('input.ck-row:checked').each(function(){
                var row = $(this).parent('td').parent('tr');
                // alert(JSON.stringify(row.data('json')));
                orders.push(row.data('json'));
                
            });

            aForm = $('form[name=send_order]');
            // aForm = $('<form>').attr('method','POST').attr('action','order/print-order');
            // aForm.append(
            //     $('<input>')
            //         .attr('type','text')
            //         .attr('name','order')
            //         .val(JSON.stringify(orders)
            //     ));
            $('input[name=order]').val(JSON.stringify(orders));
            
            aForm.submit();
            

        });

    })
</script>
@append