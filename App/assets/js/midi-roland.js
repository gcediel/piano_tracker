// Utilidades MIDI compartidas para hablar con el Roland GO:KEYS 3/5.
// El GO:KEYS recibe siempre por el canal MIDI 4 (índice 0-based = 3), según
// el documento oficial "MIDI Implementation" (Basic Channel recognized = 4).
const ROLAND_MIDI_CHANNEL = 3;

function rolandSendCC(output, cc, value) {
  if (!output) return;
  output.send([0xB0 | ROLAND_MIDI_CHANNEL, cc & 0x7F, Math.max(0, Math.min(127, value | 0))]);
}

function rolandSendProgramChange(output, pcBase1) {
  if (!output) return;
  const pc0 = Math.max(0, Math.min(127, (parseInt(pcBase1) || 1) - 1));
  output.send([0xC0 | ROLAND_MIDI_CHANNEL, pc0]);
}

// Selecciona un tono: Bank Select (MSB/LSB) si se conocen, seguido de Program Change.
function rolandSelectTone(output, tono) {
  if (!output || !tono) return;
  if (tono.msb !== null && tono.msb !== undefined && tono.lsb !== null && tono.lsb !== undefined) {
    rolandSendCC(output, 0, tono.msb);
    rolandSendCC(output, 32, tono.lsb);
  }
  rolandSendProgramChange(output, tono.pc);
}

function rolandAllSoundOff(output) { rolandSendCC(output, 120, 0); }
function rolandAllNotesOff(output) { rolandSendCC(output, 123, 0); }
function rolandResetAllControllers(output) { rolandSendCC(output, 121, 0); }

// RPN: Pitch Bend Sensitivity (semitonos, 0-24)
function rolandSetPitchBendRange(output, semitonos) {
  if (!output) return;
  const ch = ROLAND_MIDI_CHANNEL;
  output.send([0xB0 | ch, 101, 0]);
  output.send([0xB0 | ch, 100, 0]);
  output.send([0xB0 | ch, 6, Math.max(0, Math.min(24, semitonos | 0))]);
  output.send([0xB0 | ch, 38, 0]);
  output.send([0xB0 | ch, 101, 127]);
  output.send([0xB0 | ch, 100, 127]);
}

// RPN: Master Coarse Tuning (semitonos relativos, -64..+63, 64=sin cambio)
function rolandSetCoarseTuning(output, semitonosRel) {
  if (!output) return;
  const ch = ROLAND_MIDI_CHANNEL;
  output.send([0xB0 | ch, 101, 0]);
  output.send([0xB0 | ch, 100, 2]);
  output.send([0xB0 | ch, 6, Math.max(0, Math.min(127, semitonosRel | 0))]);
  output.send([0xB0 | ch, 101, 127]);
  output.send([0xB0 | ch, 100, 127]);
}
