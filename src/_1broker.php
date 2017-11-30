<?php

namespace ccxt;

class _1broker extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => '_1broker',
            'name' => '1Broker',
            'countries' => 'US',
            'rateLimit' => 1500,
            'version' => 'v2',
            'hasPublicAPI' => false,
            'hasCORS' => true,
            'hasFetchTrades' => false,
            'hasFetchOHLCV' => true,
            'timeframes' => array (
                '1m' => '60',
                '15m' => '900',
                '1h' => '3600',
                '1d' => '86400',
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27766021-420bd9fc-5ecb-11e7-8ed6-56d0081efed2.jpg',
                'api' => 'https://1broker.com/api',
                'www' => 'https://1broker.com',
                'doc' => 'https://1broker.com/?c=en/content/api-documentation',
            ),
            'requiredCredentials' => array (
                'apiKey' => true,
                'secret' => false,
            ),
            'api' => array (
                'private' => array (
                    'get' => array (
                        'market/bars',
                        'market/categories',
                        'market/details',
                        'market/list',
                        'market/quotes',
                        'market/ticks',
                        'order/cancel',
                        'order/create',
                        'order/open',
                        'position/close',
                        'position/close_cancel',
                        'position/edit',
                        'position/history',
                        'position/open',
                        'position/shared/get',
                        'social/profile_statistics',
                        'social/profile_trades',
                        'user/bitcoin_deposit_address',
                        'user/details',
                        'user/overview',
                        'user/quota_status',
                        'user/transaction_log',
                    ),
                ),
            ),
        ));
    }

    public function fetch_categories () {
        $response = $this->privateGetMarketCategories ();
        // they return an empty string among their $categories, wtf?
        $categories = $response['response'];
        $result = array ();
        for ($i = 0; $i < count ($categories); $i++) {
            if ($categories[$i])
                $result[] = $categories[$i];
        }
        return $result;
    }

    public function fetch_markets () {
        $this_ = $this; // workaround for Babel bug (not passing `this` to _recursive() call)
        $categories = $this->fetch_categories();
        $result = array ();
        for ($c = 0; $c < count ($categories); $c++) {
            $category = $categories[$c];
            $markets = $this_->privateGetMarketList (array (
                'category' => strtolower ($category),
            ));
            for ($p = 0; $p < count ($markets['response']); $p++) {
                $market = $markets['response'][$p];
                $id = $market['symbol'];
                $symbol = null;
                $base = null;
                $quote = null;
                if (($category == 'FOREX') || ($category == 'CRYPTO')) {
                    $symbol = $market['name'];
                    $parts = explode ('/', $symbol);
                    $base = $parts[0];
                    $quote = $parts[1];
                } else {
                    $base = $id;
                    $quote = 'USD';
                    $symbol = $base . '/' . $quote;
                }
                $base = $this_->common_currency_code($base);
                $quote = $this_->common_currency_code($quote);
                $result[] = array (
                    'id' => $id,
                    'symbol' => $symbol,
                    'base' => $base,
                    'quote' => $quote,
                    'info' => $market,
                    'otherfield' => array (
                        'onemore' => array (
                        ),
                    ),
                );
            }
        }
        return $result;
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $balance = $this->privateGetUserOverview ();
        $response = $balance['response'];
        $result = array (
            'info' => $response,
        );
        $currencies = array_keys ($this->currencies);
        for ($c = 0; $c < count ($currencies); $c++) {
            $currency = $currencies[$c];
            $result[$currency] = $this->account ();
        }
        $total = floatval ($response['balance']);
        $result['BTC']['free'] = $total;
        $result['BTC']['total'] = $total;
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $params = array ()) {
        $this->load_markets();
        $response = $this->privateGetMarketQuotes (array_merge (array (
            'symbols' => $this->market_id($symbol),
        ), $params));
        $orderbook = $response['response'][0];
        $timestamp = $this->parse8601 ($orderbook['updated']);
        $bidPrice = floatval ($orderbook['bid']);
        $askPrice = floatval ($orderbook['ask']);
        $bid = array ( $bidPrice, null );
        $ask = array ( $askPrice, null );
        return array (
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'bids' => array ( $bid ),
            'asks' => array ( $ask ),
        );
    }

    public function fetch_trades ($symbol) {
        throw new ExchangeError ($this->id . ' fetchTrades () method not implemented yet');
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $result = $this->privateGetMarketBars (array_merge (array (
            'symbol' => $this->market_id($symbol),
            'resolution' => 60,
            'limit' => 1,
        ), $params));
        $orderbook = $this->fetch_order_book($symbol);
        $ticker = $result['response'][0];
        $timestamp = $this->parse8601 ($ticker['date']);
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['h']),
            'low' => floatval ($ticker['l']),
            'bid' => $orderbook['bids'][0][0],
            'ask' => $orderbook['asks'][0][0],
            'vwap' => null,
            'open' => floatval ($ticker['o']),
            'close' => floatval ($ticker['c']),
            'first' => null,
            'last' => null,
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => null,
            'quoteVolume' => null,
            'info' => $ticker,
        );
    }

    public function parse_ohlcv ($ohlcv, $market = null, $timeframe = '1m', $since = null, $limit = null) {
        return [
            $this->parse8601 ($ohlcv['date']),
            floatval ($ohlcv['o']),
            floatval ($ohlcv['h']),
            floatval ($ohlcv['l']),
            floatval ($ohlcv['c']),
            null,
        ];
    }

    public function fetch_ohlcv ($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array (
            'symbol' => $market['id'],
            'resolution' => $this->timeframes[$timeframe],
        );
        if ($since)
            $request['date_start'] = $this->iso8601 ($since); // they also support date_end
        if ($limit)
            $request['limit'] = $limit;
        $result = $this->privateGetMarketBars (array_merge ($request, $params));
        return $this->parse_ohlcvs($result['response'], $market, $timeframe, $since, $limit);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        $order = array (
            'symbol' => $this->market_id($symbol),
            'margin' => $amount,
            'direction' => ($side == 'sell') ? 'short' : 'long',
            'leverage' => 1,
            'type' => $side,
        );
        if ($type == 'limit')
            $order['price'] = $price;
        else
            $order['type'] .= '_market';
        $result = $this->privateGetOrderCreate (array_merge ($order, $params));
        return array (
            'info' => $result,
            'id' => $result['response']['order_id'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        return $this->privatePostOrderCancel (array ( 'order_id' => $id ));
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $this->check_required_credentials();
        $url = $this->urls['api'] . '/' . $this->version . '/' . $path . '.php';
        $query = array_merge (array ( 'token' => $this->apiKey ), $params);
        $url .= '?' . $this->urlencode ($query);
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (array_key_exists ('warning', $response))
            if ($response['warning'])
                throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        if (array_key_exists ('error', $response))
            if ($response['error'])
                throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        return $response;
    }
}