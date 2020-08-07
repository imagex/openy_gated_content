const filters = [
  {
    name: 'capitalize',
    execute: (value) => {
      if (!value) return '';
      const newValue = value.toString().toLowerCase();
      return newValue.charAt(0).toUpperCase() + newValue.slice(1);
    },
  },
  {
    name: 'first_letter',
    execute: (value) => {
      if (!value) return '';
      return value.charAt(0).toUpperCase();
    },
  },
  {
    name: 'schedule',
    execute: (date) => {
      if (!date) return '';

      function formatTime(date) {
        const hours = date.getHours() % 12 || 12;
        let minutes = '';
        if (date.getMinutes() > 0) {
          minutes = `:${date.getMinutes() < 10 ? '0' : ''}${date.getMinutes()}`;
        }
        const morning = date.getHours() < 12 ? 'AM' : 'PM';
        let time = `${hours}${minutes} ${morning}`;
        if (time === '12 PM') {
          time = 'Noon';
        }
        return time;
      }

      const dateStart = new Date(date.value);
      const dateEnd = new Date(date.end_value);
      const now = new Date();

      let start = `${formatTime(dateStart)}–`;

      if (dateStart < now && now < dateEnd) {
        start = 'Until ';
      }

      return `${start}${formatTime(dateEnd)}`;
    },
  },
  {
    name: 'day',
    execute: (value) => new Date(value).getDate(),
  },
  {
    name: 'month',
    execute: (value) => {
      const date = new Date(value);
      const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
      ];

      return monthNames[date.getMonth()];
    },
  },
];

export default filters;
