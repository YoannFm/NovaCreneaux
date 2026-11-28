<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Format unique des erreurs de l'API : {"error": {"code", "message", "details"?}}. */
class ApiError
{
    public static function response(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    /** Refus d'une regle de reservation (voir ReservationService) : 403 banni, 409 conflit d'etat, 422 regle non respectee. */
    public static function fromReservationFailure(array $failure): JsonResponse
    {
        $status = match ($failure['code']) {
            'banned' => 403,
            'slot_full', 'already_booked', 'slot_started', 'reservation_past', 'cancel_deadline' => 409,
            default => 422,
        };

        return self::response($failure['code'], $failure['error'], $status);
    }

    /** Erreurs HTTP de Laravel (403, 404, 405, 429...) : le code suit le statut. */
    public static function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        [$code, $message] = match ($status) {
            403 => ['forbidden', 'Ce jeton n\'a pas le droit d\'effectuer cette opération.'],
            404 => ['not_found', 'Ressource introuvable.'],
            405 => ['method_not_allowed', 'Méthode non autorisée pour cette adresse.'],
            429 => ['too_many_requests', 'Trop de requêtes, réessayez plus tard.'],
            default => ['http_error', $e->getMessage() ?: 'Requête invalide.'],
        };

        return self::response($code, $message, $status)->withHeaders($e->getHeaders());
    }
}
