import 'package:flutter/material.dart';
import '../../core/theme.dart';
import 'models.dart';

class AvailabilityCalendar extends StatefulWidget {
  const AvailabilityCalendar({
    super.key,
    required this.availability,
    required this.onChanged,
    this.start,
    this.end,
  });
  final Availability availability;
  final DateTime? start, end;
  final void Function(DateTime?, DateTime?) onChanged;
  @override
  State<AvailabilityCalendar> createState() => _AvailabilityCalendarState();
}

class _AvailabilityCalendarState extends State<AvailabilityCalendar> {
  late DateTime month;
  @override
  void initState() {
    super.initState();
    final today = shopToday(widget.availability.timezone);
    month = DateTime.utc(today.year, today.month);
  }

  bool selectable(DateTime day) {
    final start = widget.start;
    if (start != null && widget.end == null && day.isAfter(start)) {
      return widget.availability.validRange(start, day);
    }
    return widget.availability.dayFree(day);
  }

  void select(DateTime day) {
    if (widget.start == null ||
        widget.end != null ||
        !day.isAfter(widget.start!)) {
      widget.onChanged(day, null);
    } else {
      widget.onChanged(widget.start, day);
    }
  }

  @override
  Widget build(BuildContext context) {
    final first = month.subtract(Duration(days: month.weekday - 1));
    final localizations = MaterialLocalizations.of(context);
    return Column(
      children: [
        Row(
          children: [
            IconButton(
              tooltip: 'Mois précédent',
              onPressed:
                  month.isAfter(
                    DateTime.utc(
                      widget.availability.from.year,
                      widget.availability.from.month,
                    ),
                  )
                  ? () => setState(
                      () => month = DateTime.utc(month.year, month.month - 1),
                    )
                  : null,
              icon: const Icon(Icons.chevron_left),
            ),
            Expanded(
              child: Text(
                localizations.formatMonthYear(month),
                textAlign: TextAlign.center,
                style: AppTypography.heading,
              ),
            ),
            IconButton(
              tooltip: 'Mois suivant',
              onPressed:
                  DateTime.utc(
                    month.year,
                    month.month + 1,
                  ).isBefore(widget.availability.to)
                  ? () => setState(
                      () => month = DateTime.utc(month.year, month.month + 1),
                    )
                  : null,
              icon: const Icon(Icons.chevron_right),
            ),
          ],
        ),
        Row(
          children: [
            for (final name in ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'])
              Expanded(child: Text(name, textAlign: TextAlign.center)),
          ],
        ),
        const SizedBox(height: 8),
        GridView.count(
          crossAxisCount: 7,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: [
            for (var i = 0; i < 42; i++)
              Builder(
                builder: (context) {
                  final day = first.add(Duration(days: i));
                  final inMonth = day.month == month.month;
                  final allowed = inMonth && selectable(day);
                  final selected = day == widget.start || day == widget.end;
                  final inside =
                      widget.start != null &&
                      widget.end != null &&
                      day.isAfter(widget.start!) &&
                      day.isBefore(widget.end!);
                  return Semantics(
                    label:
                        '${dateInput(day)}${allowed ? ' disponible' : ' indisponible'}',
                    selected: selected,
                    child: Padding(
                      padding: const EdgeInsets.all(2),
                      child: TextButton(
                        key: ValueKey('day:${dateInput(day)}'),
                        onPressed: allowed ? () => select(day) : null,
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: const Size(40, 44),
                          backgroundColor: selected
                              ? AppColors.orange
                              : inside
                              ? AppColors.orange.withValues(alpha: .14)
                              : null,
                          foregroundColor: AppColors.carbon,
                        ),
                        child: Text(
                          inMonth ? '${day.day}' : '',
                          style: TextStyle(
                            decoration: inMonth && !allowed
                                ? TextDecoration.lineThrough
                                : null,
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
          ],
        ),
        const Text(
          'Dates barrées : indisponibles. La date de fin correspond au retour et n’est pas facturée.',
        ),
        const SizedBox(height: 12),
        Text(
          'Départ : ${widget.start == null ? 'À choisir' : displayDate(dateInput(widget.start!))} · Retour : ${widget.end == null ? 'À choisir' : displayDate(dateInput(widget.end!))}',
        ),
        TextButton(
          onPressed: () => widget.onChanged(null, null),
          child: const Text('Effacer les dates'),
        ),
      ],
    );
  }
}
