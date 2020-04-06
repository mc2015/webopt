<?php

$files = [];

if ($handle = opendir('out')) {
	while (false !== ($entry = readdir($handle))) {
		if ($entry != "." && $entry != "..") {
			$files[] = $entry;
		}
	}
	closedir($handle);
}

$xmls = [];

foreach ($files as $file) {

	$open = zip_open('out/'.$file);

	if (is_numeric($open)) {
		die("Zip Open Error #: $open");
	} else {
		while($zip = zip_read($open)) {
			$xmls[] = zip_entry_read($zip , zip_entry_filesize($zip));
		}
	}


}

$out = new stdClass;

$out->items = [];

$invalidBarcodeLog = fopen('_ivalidbarcodes.txt', 'w');

$prices = [];
$rests = [];


// Получить цены
foreach ($xmls as $xml) {

	$doc = new SimpleXMLElement($xml);

	foreach ($doc->ПакетПредложений as $packets) {
		foreach ($packets->Предложения as $offers) {
			foreach ($offers->Предложение as $offer) {
				if (isset($offer->Цены)) {
					$id = (string) $offer->Ид;
					$price = $offer->Цены->Цена->ЦенаЗаЕдиницу;
					$prices[$id] = (int) $price;
				}
			}
		}
	}			
}


// Получить остатки
foreach ($xmls as $xml) {

	$doc = new SimpleXMLElement($xml);

	foreach ($doc->ПакетПредложений as $packets) {
		foreach ($packets->Предложения as $offers) {
			foreach ($offers->Предложение as $offer) {
				$good_id = (string) $offer->Ид;
				if (isset($offer->Остатки)) {
					$rest_for_inventory = [];
					foreach ($offer->Остатки as $rest) {
						$rest_for_inventory[] = [
							(string) $rest->Остаток->Склад->Ид,
							(int) $rest->Остаток->Склад->Количество
						];
					}
					$rests[$good_id] = $rest_for_inventory;
				}
			}
		}
	}			
}

// Получить каталог
foreach ($xmls as $xml) {

}


foreach ($xmls as $xml) {

	$doc = new SimpleXMLElement($xml);

	foreach ($doc->Каталог as $cat_items) {
		foreach ($cat_items->Товары as $goods) {
			foreach ($goods->Товар as $good) {
				$item = new stdClass;
				$item->id = (string) $good->Ид;
				$barcodes = explode(', ', $good->Штрихкод);
				foreach ($barcodes as $barcode_key => $barcode) {
					if (!isValidBarcode($barcode)) {
						fwrite ($invalidBarcodeLog, $barcode . "\n");
						unset($barcodes[$barcode_key]);
					}
				}
				$item->barcodes = $barcodes;
				// нет
				$item->category_id = '';
				$item->description = (string) $good->Описание;
				// нет
				$item->images_links = [];
				$item->name = (string) $good->Наименование;
				if (isset($prices[$item->id])) {
					$item->price = $prices[$item->id];
				}
				// остатки
				$item->rests = $rests[$item->id];

				$out->items[] = $item;
			}
		}
	}
}


$json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$f_out = fopen('_out.txt', 'w');

fwrite ($f_out, print_r($json, true));

fclose($invalidBarcodeLog);

fclose($f_out);

die;




function isValidBarcode($barcode) {
		//checks validity of: GTIN-8, GTIN-12, GTIN-13, GTIN-14, GSIN, SSCC
		//see: http://www.gs1.org/how-calculate-check-digit-manually
		$barcode = (string) $barcode;
		//we accept only digits
		if (!preg_match("/^[0-9]+$/", $barcode)) {
			return false;
		}
		//check valid lengths:
		$l = strlen($barcode);
		if(!in_array($l, [8,12,13,14,17,18]))
			return false;
		//get check digit
		$check = substr($barcode, -1);
		$barcode = substr($barcode, 0, -1);
		$sum_even = $sum_odd = 0;
		$even = true;
		while(strlen($barcode)>0) {
			$digit = substr($barcode, -1);
			if($even)
				$sum_even += 3 * $digit;
			else 
				$sum_odd += $digit;
			$even = !$even;
			$barcode = substr($barcode, 0, -1);
		}
		$sum = $sum_even + $sum_odd;
		$sum_rounded_up = ceil($sum/10) * 10;
		return ($check == ($sum_rounded_up - $sum));
}


