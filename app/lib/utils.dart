/// "18 Nov, 3:05 PM" in the phone's local time. (Server sends ISO times in UTC.)
String fmtDateTime(String? iso) {
  if (iso == null) return '';
  final d = DateTime.tryParse(iso)?.toLocal();
  if (d == null) return '';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  final hour12 = d.hour % 12 == 0 ? 12 : d.hour % 12;
  final minute = d.minute.toString().padLeft(2, '0');
  return '${d.day} ${months[d.month - 1]}, $hour12:$minute ${d.hour < 12 ? 'AM' : 'PM'}';
}

String modeLabel(String? mode) => mode == 'bike' ? 'Bike / Scooty' : 'Car / Rickshaw';

/// Plain-language status for a passenger's ride request.
String rideStatusLabel(Map ride) {
  final group = ride['group'] as Map?;
  switch (ride['status']) {
    case 'requested':
      return 'Finding women on your route';
    case 'matched':
      switch (group?['status']) {
        case 'driver_accepted':
          return 'Driver confirmed';
        case 'in_progress':
          return 'Trip in progress';
        case 'completed':
          return 'Completed';
        default:
          return 'Group found, waiting for a driver';
      }
    case 'cancelled':
      return 'Cancelled';
    case 'no_match':
      return 'No match found';
    case 'completed':
      return 'Completed';
    default:
      return '${ride['status']}';
  }
}
