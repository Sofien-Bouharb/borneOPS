from fastapi import WebSocket
from starlette.websockets import WebSocketDisconnect


class FastApiWebSocketAdapter:
    """
    The ocpp library was written against the standalone `websockets` package's
    connection object, which exposes async recv() / send(message). FastAPI's
    WebSocket instead exposes receive_text() / send_text(). This adapter
    bridges the two so ChargePoint.start() can drive a FastAPI WebSocket
    without any changes to the ocpp library itself.
    """

    def __init__(self, websocket: WebSocket):
        self._websocket = websocket

    async def recv(self) -> str:
        try:
            return await self._websocket.receive_text()
        except WebSocketDisconnect as e:
            from websockets.exceptions import ConnectionClosed
            raise ConnectionClosed(rcvd=None, sent=None) from e

    async def send(self, message: str) -> None:
        await self._websocket.send_text(message)
