class TimeFormatter {
  constructor(time, options = {}) {
    const {
      lang = navigator.languages[1] || navigator.language || "en-US",
      long = true,
      full = true,
      relative = false,
    } = options;

    this.full = Boolean(full);
    this.long = Boolean(long);
    this.lang = String(lang);
    this.relative = Boolean(relative);
    this.currentDate = new Date();

    if (time instanceof Date) {
      this.time = time;
    } else if (typeof time === "number") {
      const timestamp =
        time.toString().length < Date.now().toString().length
          ? time * 1000
          : time;
      this.time = new Date(timestamp);
    } else {
      throw new Error("Le paramètre 'time' doit être un nombre ou une instance de Date.");
    }
  }

  formatNumber(number) {
    return number < 10 ? "0" + number : number.toString();
  }

  getDocumentLang() {
    return this.lang;
  }

  format() {
    return this.relative ? this.formatRelativeTime() : (this.full ? this.formatFullTime() : this.formatTime());
  }

  formatTime() {
    const length = this.long ? "long" : "short";
    const yearOption = { day: "numeric", month: length, year: "numeric" };
    const monthOption = { day: "numeric", month: length };
    const weekOption = { weekday: length };

    const sameYear = this.time.getFullYear() === this.currentDate.getFullYear();
    const timeDifferenceInDays = Math.floor(
      ((this.time.getTime() - this.currentDate.getTime())) / (24 * 60 * 60 * 1000)
    );

    const hours = this.formatNumber(this.time.getHours());
    const minutes = this.formatNumber(this.time.getMinutes());

    const lang = this.getDocumentLang();

    let formattedTime =
      sameYear &&
      this.time.toLocaleDateString() === this.currentDate.toLocaleDateString()
        ? `${hours}:${minutes}`
        : sameYear
          ? new Intl.DateTimeFormat(
              lang,
              timeDifferenceInDays > 6 ? monthOption : weekOption
            ).format(this.time)
          : new Intl.DateTimeFormat(lang, yearOption).format(this.time);

    formattedTime = formattedTime[0].toUpperCase() + formattedTime.slice(1);

    if (
      length === "long" &&
      lang.toLowerCase().startsWith("fr") &&
      timeDifferenceInDays > 6
    ) {
      return `le ${formattedTime}`;
    }

    return formattedTime;
  }

  formatFullTime() {
    const length = this.long ? "long" : "short";
    const yearOption = { day: "numeric", month: length, year: "numeric" };
    const monthOption = { day: "numeric", month: length };
    const weekOption = { weekday: length };

    const sameYear = this.time.getFullYear() === this.currentDate.getFullYear();
    const timeDifferenceInDays = Math.floor(
      (this.time.getTime() - this.currentDate.getTime()) / (24 * 60 * 60 * 1000)
    );
    
    

    const hours = this.formatNumber(this.time.getHours());
    const minutes = this.formatNumber(this.time.getMinutes());

    const lang = this.getDocumentLang();

    let formattedTime =
      sameYear &&
      this.time.toLocaleDateString() === this.currentDate.toLocaleDateString()
        ? `${hours}:${minutes}`
        : sameYear
          ? `${new Intl.DateTimeFormat(
              lang,
              timeDifferenceInDays > 6 ? monthOption : weekOption
            ).format(this.time)}, ${hours}:${minutes}`
          : new Intl.DateTimeFormat(lang, yearOption).format(this.time);

    formattedTime = formattedTime[0].toUpperCase() + formattedTime.slice(1);

    if (
      length === "long" &&
      lang.toLowerCase().startsWith("fr") &&
      timeDifferenceInDays > 6
    ) {
      return `le ${formattedTime}`;
    }

    return formattedTime;
  }

  formatRelativePeriod(maxUnit = "year", minUnit = "second") {
    const timestamp = this.time.getTime();
    const now = Date.now();
    const diffInSeconds = Math.round((timestamp - now) / 1000);

    const unitsStartTime = {
      second: now,
      minute: new Date().setSeconds(0, 0),
      hour: new Date().setMinutes(0, 0, 0),
      day: new Date().setHours(0, 0, 0, 0),
      week: (() => {
        const date = new Date();
        const day = date.getDay();
        const diff = date.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(date.setDate(diff)).setHours(0, 0, 0, 0);
      })(),
      month: new Date(new Date().getFullYear(), new Date().getMonth(), 1).setHours(0, 0, 0, 0),
      year: new Date(new Date().getFullYear(), 0, 1).setHours(0, 0, 0, 0),
    };

    const lang = this.getDocumentLang();
    const rtf = new Intl.RelativeTimeFormat(lang, { numeric: "auto" });

    const unitOrder = ["second", "minute", "hour", "day", "week", "month", "year"];
    const maxUnitIndex = unitOrder.indexOf(maxUnit);
    const minUnitIndex = unitOrder.indexOf(minUnit);

    let chosenUnit, chosenValue;

    for (const unit of unitOrder) {
      const valueInSeconds = TimeFormatter.timeUnits[unit];
      const absValue = Math.abs(diffInSeconds);
      if (absValue < valueInSeconds || unit === "year") {
        chosenUnit = unit;
        chosenValue = Math.round(diffInSeconds / valueInSeconds);
        break;
      }
    }

    const chosenUnitIndex = unitOrder.indexOf(chosenUnit);

    if (chosenUnitIndex > maxUnitIndex) {
      return this.full ? this.formatFullTime() : this.formatTime();
    }

    if (chosenUnitIndex < minUnitIndex) {
      chosenUnit = minUnit;
      chosenValue = Math.round(diffInSeconds / TimeFormatter.timeUnits[minUnit]);
    }

    return rtf.format(chosenValue, chosenUnit);
  }

  formatRelativeTime() {
    const now = new Date();
    const timestamp = this.time.getTime() - 2000;
    const diffInSeconds = Math.floor((timestamp - now.getTime()) / 1000);

    const style = this.long ? "long" : "short";
    const rtf = new Intl.RelativeTimeFormat(this.getDocumentLang(), {
      numeric: "auto",
      style,
    });

    const timeFrames = [
      { unit: "year", seconds: 31557600 },
      { unit: "month", seconds: 2629800 },
      { unit: "week", seconds: 604800 },
      { unit: "day", seconds: 86400 },
      { unit: "hour", seconds: 3600 },
      { unit: "minute", seconds: 60 },
      { unit: "second", seconds: 1 },
    ];

    for (const frame of timeFrames) {
      const elapsed = diffInSeconds / frame.seconds;
      if (Math.abs(elapsed) >= 1) {
        return rtf.format(Math.round(elapsed), frame.unit);
      }
    }

    return rtf.format(0, "second");
  }

  static timeUnits = {
    second: 1,
    minute: 60,
    hour: 3600,
    day: 86400,
    week: 604800,
    month: 2629800,
    year: 31557600,
  };
}

class NumberFormatter {
  constructor(number, lang = navigator.languages[1] || navigator.language || "en-US",) {
    if (typeof number !== "number") {
      throw new Error("Le paramètre 'number' doit être un nombre.");
    }
    this.number = number;
    this.lang = String(lang);
  }

  formatNumber() {
    const formatter = new Intl.NumberFormat(this.lang, {
      notation: "compact",
      maximumFractionDigits: 1,
    });
    return formatter.format(this.number);
  }

  formatTime() {
    const nombre = this.number;
    const conversions = [
      { seuil: 60, unite: "second", diviseur: 1 },
      { seuil: 3600, unite: "minute", diviseur: 60 },
      { seuil: 86400, unite: "hour", diviseur: 3600 },
      { seuil: 604800, unite: "day", diviseur: 86400 },
      { seuil: 2629800, unite: "week", diviseur: 604800 },
      { seuil: 31557600, unite: "month", diviseur: 2629800 },
      { seuil: Infinity, unite: "year", diviseur: 31557600 },
    ];

    const { seuil, unite, diviseur } =
      conversions.find(({ seuil }) => nombre < seuil) ||
      conversions[conversions.length - 1];

    const quotient = seuil === Infinity ? nombre : Math.floor(nombre / diviseur);

    const options = {
      style: "unit",
      unit: unite,
    };

    const formateur = new Intl.NumberFormat(this.lang, options);
    return formateur.format(quotient);
  }
}

export { TimeFormatter, NumberFormatter };