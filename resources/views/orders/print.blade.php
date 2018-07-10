<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>INVOICE</title>
	<style>
		body{
			/*font-family: 'open sans';*/
			
			font-size: 10px;
		}
		table{
			font-size: inherit;
		}
	    table.table-product, table.table-total {
	        border-collapse: collapse;
	      }
	     table.table-product{
	     	margin-bottom: 10mm;
	     }
	    table.table-product th, table.table-product td {
	        border:0.5px solid black;
	        padding: 0px;
	        text-align: left;
	      }
	    table.table-product th{
	    	padding-top: 10px;
	    	padding-bottom: 10px;
	    	text-align: center;
	    }
	    table.table-product td {
	        padding: 5px;
	        vertical-align: top;
	    }
      	.header,
		.footer {
		    width: 100%;
		    text-align: center;
		    position: fixed;
		}
		.header {
		    top: 0px;
		}
		.footer {
		    bottom: 0px;
		}
		.pagenum:before {
		    content: counter(page);
		}
	</style> 
</head>
<body>
	<div style="text-align: center;margin-bottom: 10mm;" >
		<h4 style="margin:0;padding:0;">INVOICE</h4>
	</div>

	<div class="content"  >
		<table>
			<tbody>
				<tr>
					<td>Nomor</td>
					<td>:</td>
					<td>{{$data->id}}</td>
				</tr>
				<tr>
					<td>Tanggal</td>
					<td>:</td>
					<td>{{$data->tanggal}}</td>
				</tr>
			</tbody>
		</table>
		<br/>
        <table class="table-product">
			<thead></thead>
			<tbody>
				{!! $data->detail_pesanan !!}
			</tbody>
		</table>		
		<table>
			<tbody>
				<tr>
					<td style="width:50%;vertical-align:top;" >
						{!! $data->detail_pengiriman !!}
					</td>
					<td style="width:5%;" ></td>
					<td style="width:45%;vertical-align:top;" >
						{!! $data->catatan !!}
					</td>
				</tr>
			</tbody>
		</table>
    </div>
	
	
</body>
</html>