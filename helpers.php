<?php
/**
 * Attempts to authenticate an existing member with their Member ID and Last Name, or Email and Password
 *
 * @param  [type] $args [description]
 * @return [type]       [description]
 */
function centaman_member_authenticate( $args ) {
	$members = new \Zao\ZCSDK\Services\Member_Services();

	$auth = $members->authenticate( $args );

	return $auth->is_authenticated();
}

function centaman_get_member( $member_id ) {
	return ( new Zao\ZCSDK\Services\Member_Services() )->get( $member_id );
}

/**
 * Returns all timed ticket booking types.
 * If a Booking ID is passed, returns tickets for just that type.
 *
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
function centaman_get_timed_ticket_types( $booking_id = null ) {
	return ( new Zao\ZCSDK\Services\Ticket_Services() )->get_tickets( $booking_id );
}

/**
 * Creates a customer Contact record.
 * Expects an array with First Name, Last Name, Email and Address.
 *
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
function centaman_create_customer( $args ) {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->create_contact( $args );
}

/**
 * Creates a customer Contact record.
 * Expects an array with First Name, Last Name, Email and Address.
 *
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
function centaman_get_company_info( $args ) {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->get_company_info( $args );
}

function centaman_get_timed_ticket_booking_types( $booking_type_id = 0, $start = '', $end = '' ) {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->get_timed_ticket_types( $booking_type_id, $start, $end );
}


function centaman_get_remaining_event_capacity( $timed_ticket_type_id, $start_date ) {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->get_capacity( $timed_ticket_type_id, $start_date );
}

/**
 * Creates transaction in Centaman
 *
 * @param  [type] $args [description]
 * @return [type]       [description]
 */
function centaman_create_transaction( $args ) {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->create_transaction( $args );
}

/**
 * This function is called to temporarily reserve tickets while the customer completes
 * the transaction.
 *
 * Tickets that have been reserved reduce the Vacancy count for other web
 * sessions as well as desktop Point Of Sale.
 *
 * The temporary reservation is removed when the customer completes purchase and the reservation
 * becomes permanent or when they end the browser session
 *
 * @param array $args Expects an array of arrays with the following parameters:
 *                    Example:
 * array(
 *    array(
 *        // TimedTicketTypeId: (int, Required) TimedTicketType Id (Centaman Internal Id).
 *        'TimedTicketTypeId' => '2',
 *
 *        // TicketId: (int, Required) Timed Ticket Id.
 *        'TimedTicketId' => '2',
 *
 *        // NumberOfTickets: (int, Required) Number of spots reserving for TimedTicket.
 *        'NumberOfTickets' => '2',
 *    ),
 * )
 * @return mixed
 */
function centaman_hold_spot( $args ) {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->hold_spot( $args );
}

/**
 * This method is for releasing the held spots.
 *
 * Tickets that have been released the Vacancy count for other web sessions
 * as well as desktop Point Of Sale.
 *
 * The temporary reservation is removed when the customer completes purchase and
 * the reservation becomes permanentor when they end the browser session
 *
 * @param array $args Expects an array of arrays with the following parameters:
 *                    Example:
 * array(
 *    array(
 *        // TimedTicketTypeId: (int, Required) TimedTicketType Id (Centaman Internal Id).
 *        'TimedTicketTypeId' => '2',
 *
 *        // TicketId: (int, Required) Timed Ticket Id.
 *        'TimedTicketId' => '2',
 *
 *        // NumberOfTickets: (int, Required) Number of spots reserving for TimedTicket.
 *        'NumberOfTickets' => '2',
 *    ),
 * )
 * @return mixed
 */
function centaman_release_hold( $args ) {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->release_hold( $args );
}

/**
 * This method will return the Foreign Currency setup in the system and return the exchange rate.
 *
 * @since  [since]
 *
 * @return [type]  [description]
 */
function centaman_foreign_currency() {
	return ( new \Zao\ZCSDK\Services\Ticket_Services() )->foreign_currency();
}

/**
 * Get the last api request instance, to use it's methods.
 *
 * @return null|\Zao\ZCSDK\Services\API_Request
 */
function centaman_get_last_api_request_instance() {
	return \Zao\ZCSDK\Services\API_Request::get_last_instance();
}
