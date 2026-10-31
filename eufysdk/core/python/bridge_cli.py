#!/usr/bin/env python3
"""Short-lived WebSocket JSON RPC client for mega-yfue bridge (schema 1)."""
import argparse
import json
import sys
import websocket


def rpc(url, cmd, fields, timeout=18):
    ws = websocket.create_connection(url, timeout=timeout, enable_multithread=True)
    try:
        ws.send(json.dumps(dict(id=731, cmd=cmd, **fields)))
        for _ in range(80):
            packet = json.loads(ws.recv())
            if packet.get('id') == 731:
                return packet
        raise RuntimeError('Response not received')
    finally:
        ws.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--url', required=True)
    parser.add_argument('--cmd', required=True, choices=('auth.status','auth.submit','auth.retrigger','devices.list','device.state','device.properties','device.set','device.action'))
    parser.add_argument('--fields', default='{}')
    args = parser.parse_args()
    fields = json.loads(args.fields)
    if not isinstance(fields, dict):
        raise ValueError('fields must be an object')
    result = rpc(args.url, args.cmd, fields)
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result.get('ok') else 2


if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception as exc:
        print(json.dumps({'ok': False, 'error': str(exc)}))
        sys.exit(2)
