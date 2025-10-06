<?php

use Grocy\Helpers\BaseBarcodeLookupPlugin;
use Grocy\Plugins\LocalBarcodeDatabase;
use GuzzleHttp\Client;
/*
	This class must extend BaseBarcodeLookupPlugin (in namespace Grocy\Helpers)
*/

class LocalBarcodeLookupPlugin extends BaseBarcodeLookupPlugin
{
	/*
		To use this plugin, configure it in data/config.php like this:
		Setting('STOCK_BARCODE_LOOKUP_PLUGIN', 'DemoBarcodeLookupPlugin');
	*/

	/*
		To try it:

		Call the API function at /api/stock/barcodes/external-lookup/{barcode}

		Or use the product picker workflow "External barcode lookup"

		When you also add ?add=true as a query parameter to the API call,
		on a successful lookup the product is added to the database and in the output
		the new product id is included (automatically, nothing to do here in the plugin)
	*/

	/*
		Provided references:

		$this->Locations contains all locations
		$this->QuantityUnits contains all quantity units
		$this->UserSettings contains all user settings
	*/

	/*
		Useful hints:

		Get a quantity unit by name:
		$quantityUnit = FindObjectInArrayByPropertyValue($this->QuantityUnits, 'name', 'Piece');

		Get a location by name:
		$location = FindObjectInArrayByPropertyValue($this->Locations, 'name', 'Fridge');
	*/

	// Provide a name
	public const PLUGIN_NAME = 'LocalBarcodeLookup';

	/*
		This class must implement the protected abstract function ExecuteLookup($barcode),
		which is called with the barcode that needs to be looked up and must return an
		associative array of the product model (see the "products" database table for all available properties/columns)
		or null when nothing was found for the barcode:
		[
			// Required properties:
			'name' => '',
			'location_id' => 1, // A valid id of a location object, check against $this->Locations
			'qu_id_purchase' => 1, // A valid id of a quantity unit object, check against $this->QuantityUnits
			'qu_id_stock' => 1, // A valid id of a quantity unit object, check against $this->QuantityUnits

			// Required virtual properties (not part of the product object, will be automatically handled as needed):
			'__qu_factor_purchase_to_stock' => 1, // Normally 1 when quantity unit stock and purchase is the same
			'__barcode' => $barcode // The barcode of the product, maybe just pass through $barcode or manipulate it if necessary

			// Optional virtual properties (not part of the product object, will be automatically handled as needed):
			'__image_url' => '' // When provided, the corresponding image will be downloaded and set as the product picture
		]
	*/
	protected function getDatabaseService()
	{
		return LocalBarcodeDatabase::getInstance();
	}

	protected function ColesLookup($barcode)
	{
		$item = $this->getDatabaseService()->get_item("Coles_Items", "barcode", $barcode);
		$imageUrl = '';
		$name = '';

		if ($item) {
			$name = $item['name'];
			$imageUrl = $item['image_front_full'];

			// Take the preset user setting or otherwise simply the first existing location
			$shoppingLocationId = $this->getDatabaseService()->get_shopping_location_by_name('Coles')?->id;

			$locationId = $this->getDatabaseService()->get_location_by_name('Coles', $item['category'])?->id;

			if ($locationId == null) {
				$locationId = $this->Locations[0]->id;
				if ($this->UserSettings['product_presets_location_id'] != -1) {
					$locationId = $this->UserSettings['product_presets_location_id'];
				}
			}

			// Take the preset user setting or otherwise simply the first existing quantity unit
			$quId = $this->QuantityUnits[0]->id;
			if ($this->UserSettings['product_presets_qu_id'] != -1) {
				$quId = $this->UserSettings['product_presets_qu_id'];
			}

			return [
				'name' => $name,
				'location_id' => $locationId,
				'shopping_location_id' => $shoppingLocationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'__qu_factor_purchase_to_stock' => 1,
				'__barcode' => $barcode,
				'__image_url' => $imageUrl
			];
		} else {
			return null;
		}
	}

	protected function WoolworthsLookup($barcode)
	{
		$item = $this->getDatabaseService()->get_item("Woolworths_Items", "barcode", $barcode);
		$imageUrl = "";
		if ($item) {
			$name = $item['name'];
			$imageUrl = $item['image'];
			// Take the preset user setting or otherwise simply the first existing location
			$shoppingLocationId = $this->getDatabaseService()->get_shopping_location_by_name('Woolworths')?->id;

			$locationId = $this->getDatabaseService()->get_location_by_name('Woolworths', $item['category'])?->id;

			if ($locationId == null) {
				$locationId = $this->Locations[0]->id;
				if ($this->UserSettings['product_presets_location_id'] != -1) {
					$locationId = $this->UserSettings['product_presets_location_id'];
				}
			}
			// Take the preset user setting or otherwise simply the first existing quantity unit
			$quId = $this->QuantityUnits[0]->id;
			if ($this->UserSettings['product_presets_qu_id'] != -1) {
				$quId = $this->UserSettings['product_presets_qu_id'];
			}

			return [
				'name' => $name,
				'location_id' => $locationId,
				'shopping_location_id' => $shoppingLocationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'__qu_factor_purchase_to_stock' => 1,
				'__barcode' => $barcode,
				'__image_url' => $imageUrl
			];
		} else {
			return null;
		}
	}

	protected function AldiLookup($barcode)
	{
		$item = $this->getDatabaseService()->get_item("Aldi_Items", "barcode", $barcode);
		$imageUrl = "";
		if ($item) {
			$name = $item['name'];

			$image_urls_json = $item['image_urls'];
			if (!empty($image_urls_json)) {
				// Decode JSON into PHP array
				$image_urls = json_decode($image_urls_json, true);

				// Grab the first value safely
				$imageUrl = $image_urls[0] ?? null;
			}


			// Take the preset user setting or otherwise simply the first existing location
			$shoppingLocationId = $this->getDatabaseService()->get_shopping_location_by_name('Aldi')?->id;

			$locationId = $this->getDatabaseService()->get_location_by_name('Aldi', $item['category'])?->id;

			if ($locationId == null) {
				$locationId = $this->Locations[0]->id;
				if ($this->UserSettings['product_presets_location_id'] != -1) {
					$locationId = $this->UserSettings['product_presets_location_id'];
				}
			}
			// Take the preset user setting or otherwise simply the first existing quantity unit
			$quId = $this->QuantityUnits[0]->id;
			if ($this->UserSettings['product_presets_qu_id'] != -1) {
				$quId = $this->UserSettings['product_presets_qu_id'];
			}

			return [
				'name' => $name,
				'location_id' => $locationId,
				'shopping_location_id' => $shoppingLocationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'__qu_factor_purchase_to_stock' => 1,
				'__barcode' => $barcode,
				'__image_url' => $imageUrl
			];
		} else {
			return null;
		}
	}

	protected function AldiLookupByName($name, $barcode)
	{
		// Split $name into words by spaces
		$words = preg_split('/\s+/', trim($name));

		// Prepare the array for get_item_Like
		$searchCriteria = [];
		foreach ($words as $word) {
			$searchCriteria[] = ['name' => $word];
		}

		// Call your function for each word
		$items = [];
		$items = $this->getDatabaseService()->get_item_Like_MultiWord('Aldi_Items', 'name', $name);
		//foreach ($searchCriteria as $criteria) {
		//	$items[] = $this->getDatabaseService()->get_item_Like('Aldi_Items', $criteria);
		//}

		$imageUrl = "";
		$itemsToReturn = [];
		if ($items) {
			foreach ($items as $item) {
				if ($item) {
					$name = $item['name'];

					$image_urls_json = $item['image_urls'];
					if (!empty($image_urls_json)) {
						// Decode JSON into PHP array
						$image_urls = json_decode($image_urls_json, true);

						// Grab the first value safely
						$imageUrl = $image_urls[0] ?? null;
					}


					// Take the preset user setting or otherwise simply the first existing location
					$shoppingLocationId = $this->getDatabaseService()->get_shopping_location_by_name('Aldi')?->id;

					$locationId = $this->getDatabaseService()->get_location_by_name('Aldi', $item['category'])?->id;

					if ($locationId == null) {
						$locationId = $this->Locations[0]->id;
						if ($this->UserSettings['product_presets_location_id'] != -1) {
							$locationId = $this->UserSettings['product_presets_location_id'];
						}
					}
					// Take the preset user setting or otherwise simply the first existing quantity unit
					$quId = $this->QuantityUnits[0]->id;
					if ($this->UserSettings['product_presets_qu_id'] != -1) {
						$quId = $this->UserSettings['product_presets_qu_id'];
					}

					$itemsToReturn[] = [
						'name' => $name,
						'location_id' => $locationId,
						'shopping_location_id' => $shoppingLocationId,
						'qu_id_purchase' => $quId,
						'qu_id_stock' => $quId,
						'__qu_factor_purchase_to_stock' => 1,
						'__barcode' => $barcode,
						'__image_url' => $imageUrl
					];
				}
			}

			return $itemsToReturn;
		} else {
			return null;
		}
	}

	protected function OpenFoodFactsLookup($barcode)
	{
		$productNameFieldLocalized = 'product_name_' . substr(GROCY_LOCALE, 0, 2);

		$webClient = new Client(['http_errors' => false]);
		$response = $webClient->request('GET', 'https://world.openfoodfacts.org/api/v2/product/' . preg_replace('/[^0-9]/', '', $barcode) . '?fields=product_name,image_url,' . $productNameFieldLocalized, ['headers' => ['User-Agent' => 'GrocyOpenFoodFactsBarcodeLookupPlugin/1.0 (https://grocy.info)']]);
		$statusCode = $response->getStatusCode();

		// Guzzle throws exceptions for connection errors, so nothing to do on that here

		$data = json_decode($response->getBody());
		if ($statusCode == 404 || $data->status != 1) {
			// Nothing found for the given barcode
			return null;
		} else {
			$imageUrl = '';
			if (isset($data->product->image_url) && !empty($data->product->image_url)) {
				$imageUrl = $data->product->image_url;
			}

			// Take the preset user setting or otherwise simply the first existing location
			$locationId = $this->Locations[0]->id;
			if ($this->UserSettings['product_presets_location_id'] != -1) {
				$locationId = $this->UserSettings['product_presets_location_id'];
			}

			// Take the preset user setting or otherwise simply the first existing quantity unit
			$quId = $this->QuantityUnits[0]->id;
			if ($this->UserSettings['product_presets_qu_id'] != -1) {
				$quId = $this->UserSettings['product_presets_qu_id'];
			}

			// Use the localized product name, if provided
			$name = $data->product->product_name;
			if (isset($data->product->$productNameFieldLocalized) && !empty($data->product->$productNameFieldLocalized)) {
				$name = $data->product->$productNameFieldLocalized;
			}

			// Remove non-ASCII characters in product name (whyever a product name should have them at all)
			$name = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß ]/', '', $name);

			return [
				'name' => $name,
				'location_id' => $locationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'__qu_factor_purchase_to_stock' => 1,
				'__barcode' => $barcode,
				'__image_url' => $imageUrl
			];
		}
	}

	protected function SaveSelectedBarcode($product)
	{
		if ($product) {
			// Save the product to the database
			$this->getDatabaseService()->update_item('Aldi_Items', 'name', $product['name'], ['barcode' => $product['__barcode']]);
			$AldiItem = $this->AldiLookup($product['__barcode']);
			if ($AldiItem) {
				return $AldiItem;
			}

			return $product;
		} else {
			return null;
		}
	}

	protected function ExecuteLookup($barcode)
	{
		if ($barcode === 'nothing') {
			// Demonstration when nothing is found
			return null;
		} elseif ($barcode === 'error') {
			// Demonstration when an error occurred
			throw new \Exception('This is the error message from the plugin...');
		} else {
			$items = [];

			$WoolworthsItem = $this->WoolworthsLookup($barcode);
			if ($WoolworthsItem) {
				return $WoolworthsItem;
			}

			$AldiItem = $this->AldiLookup($barcode);
			if ($AldiItem) {
				return $AldiItem;
			}

			$ColesItem = $this->ColesLookup($barcode);
			if ($ColesItem) {
				return $ColesItem;
			}

			$OpenFoodFactsItem = $this->OpenFoodFactsLookup($barcode);
			if ($OpenFoodFactsItem) {
				$AldiItemsFound = $this->AldiLookupByName($OpenFoodFactsItem['name'], $barcode);
				if ($AldiItemsFound && count($AldiItemsFound) > 0) {
					// Return the first item found
					return $AldiItemsFound;
				} else {
					return $OpenFoodFactsItem;
				}
			}

			//if (count($items) == 0) {
			//	return null;
			//}

			return null;

			/*$item = $this->getDatabaseService()->get_item("Coles_Items", "barcode", $barcode);
			
			$imageUrl = '';
			$name = '';

			if ($item) {
				$name = $item['name'];
			} else {
				// Demonstration when something is found
				$item = $this->getDatabaseService()->get_item("Woolworths_Items", "barcode", $barcode);
				if ($item) {
					$name = $item['name'];
				} else {
					// Not found in either database, create a generic name
					return null;
				}
			}


			// Take the preset user setting or otherwise simply the first existing location
			$locationId = $this->Locations[0]->id;
			if ($this->UserSettings['product_presets_location_id'] != -1) {
				$locationId = $this->UserSettings['product_presets_location_id'];
			}

			// Take the preset user setting or otherwise simply the first existing quantity unit
			$quId = $this->QuantityUnits[0]->id;
			if ($this->UserSettings['product_presets_qu_id'] != -1) {
				$quId = $this->UserSettings['product_presets_qu_id'];
			}

			return [
				'name' => $name,
				'location_id' => $locationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'__qu_factor_purchase_to_stock' => 1,
				'__barcode' => $barcode,
				'__image_url' => $imageUrl
			];*/
		}
	}
}
