<?php
/** Port of cloth_app/delhivery_utils.py and the Order post_save shipment signal. */
final class Delhivery
{
    private static function token(): string { return (string)config('delhivery.api_token', ''); }
    private static function base(): string { return rtrim((string)config('delhivery.base_url', 'https://track.delhivery.com'), '/'); }

    public static function cleanAddress(?string $a): string
    {
        if (!$a) return '';
        return trim(preg_replace('/\s+/', ' ', str_replace("\n", ' ', $a)));
    }

    /** Signal port: called (after the response) for every newly created order. */
    public static function createForOrder(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order || $order->raw('awb_number')) return;
        if (self::token() === '') return;
        $data = Order::parseAddress($order->raw('address'));
        $name = $data['first_name'] ?? ($order->raw('custom_name') ?: 'Customer');
        $phone = $data['phone'] ?? ($order->raw('custom_number') ?: '9999999999');
        $pincode = $data['pincode'] ?? '110001';
        $state = $data['state'] ?? 'Delhi';
        $full = $data['address'] ?? '';
        $words = preg_split('/\s+/', trim($full));
        $city = $full !== '' ? end($words) : 'Delhi';

        [$resp, $awb, $ref] = self::createShipment($order, $name, $phone, $pincode, $city, $state);
        if ($awb) Order::updateWhere(['id' => $orderId], ['awb_number' => $awb, 'shipment_id' => $ref, 'shipment_status' => 'Created']);
        else Order::updateWhere(['id' => $orderId], ['shipment_status' => 'Failed']);
    }

    public static function createShipment(Order $order, string $name, string $phone, string $pincode, string $city, string $state): array
    {
        $cod = $order->raw('payment_method') === 'Cash On Delivery';
        $shipment = ['shipments' => [[
            'name' => $name, 'add' => self::cleanAddress($order->raw('address')), 'pin' => $pincode, 'city' => $city, 'state' => $state,
            'phone' => $phone, 'order' => (string)$order->raw('order_id'),
            'products_desc' => $order->product ? (string)$order->product : 'Custom Product',
            'quantity' => (int)$order->raw('quantity'),
            'cod_amount' => $cod ? (string)$order->total_price : '0',
            'payment_mode' => $cod ? 'COD' : 'Prepaid',
        ]]];
        try {
            $r = Http::postForm(self::base() . '/api/cmu/create.json', ['format' => 'json', 'data' => json_encode($shipment)],
                ['Authorization' => 'Token ' . self::token()], 10);
            $data = $r['json'];
            if (!is_array($data) || !empty($data['error'])) return [$data, null, null];
            $pkg = $data['packages'][0] ?? null;
            if ($pkg) return [$data, $pkg['waybill'] ?? null, $pkg['refnum'] ?? null];
            return [$data, null, null];
        } catch (Throwable $e) {
            log_error('Delhivery create: ' . $e->getMessage());
            return [null, null, null];
        }
    }

    public static function track(string $awb): ?string
    {
        if (self::token() === '') return null;
        try {
            $r = Http::get('https://track.delhivery.com/api/v1/packages/json/?waybill=' . rawurlencode($awb), ['Authorization' => 'Token ' . self::token()], 10);
            return $r['json']['ShipmentData'][0]['Shipment']['Status']['Status'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function mapStatus(string $s, ?Order $order = null): string
    {
        if ($order && $order->raw('status') === 'Cancelled') return 'Cancelled';
        return match ($s) {
            'Manifested' => 'Ordered',
            'In Transit' => 'Shipped',
            'Out for Delivery' => 'Out for Delivery',
            'Delivered' => 'Delivered',
            'Cancelled', 'RTO' => 'Cancelled',
            default => 'Processing',
        };
    }

    /** Profile page background tracking refresh. */
    public static function refreshOrders(array $ids): void
    {
        foreach (Order::filter(['id__in' => $ids])->get() as $order) {
            $awb = $order->raw('awb_number');
            if (!$awb || in_array($order->raw('status'), ['Delivered', 'Cancelled'], true)) continue;
            $st = self::track($awb);
            if (!$st) continue;
            $new = self::mapStatus($st, $order);
            if ($order->raw('status') === 'Cancelled') continue;
            if ($order->raw('status') !== $new || $order->raw('shipment_status') !== $st) {
                Order::updateWhere(['id' => $order->pk()], ['status' => $new, 'shipment_status' => $st, 'updated_at' => now_utc_str()]);
            }
        }
    }

    public static function cancel(string $awb): ?array
    {
        if (self::token() === '') return null;
        try {
            return Http::postJson('https://track.delhivery.com/api/p/edit', ['waybill' => $awb, 'cancellation' => 'true'], ['Authorization' => 'Token ' . self::token()], 10)['json'];
        } catch (Throwable) {
            return null;
        }
    }
}
