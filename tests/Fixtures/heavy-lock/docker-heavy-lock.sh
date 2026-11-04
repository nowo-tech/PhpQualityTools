#!/usr/bin/env sh
# docker-heavy-lock.sh: generated from nowo-tech/php-quality-tools bin/heavy-lock (workspace variant); edit it there.
# nowo-tech/php-quality-tools — heavy-lock
#
# Machine-wide lock for heavy jobs (test suites with coverage, e2e, release checks, Docker builds) on a shared developer
# machine. Up to DOCKER_HEAVY_SLOTS jobs run at once, at most one per group (the jobs of one repository share its test
# database, coverage files and stacks), and a slot is handed out only while the 1-minute load average is low and enough
# memory is available. Every waiter takes a ticket; the oldest live ticket goes first, so a job that releases and
# immediately re-acquires queues behind everyone already waiting. POSIX sh. Run with --help for the commands and variables.
#
# The nowo-tech workspace helper docker-heavy-lock.sh is this file with VARIANT_IGNORE_OWN_REPO=1 (the repository that
# holds the script is never a group), so both copies share one set of files and rules on the same machine:
#   - slot 1 is $DOCKER_HEAVY_LOCK_DIR, slot N is $DOCKER_HEAVY_LOCK_DIR.N; <slot>/owner holds the label, acquired-at
#     (UTC), holder PID ("-" when unknown), acquired-at epoch and group;
#   - <queue>/[0-]<epoch-ns>-<pid> holds the label and group of a waiter;
#   - <group dir>/<name>-<cksum> is the group token (group, PID, epoch, slot), taken with mkdir before the slot, so two
#     jobs of one group can never both pass the group check.
# Every slot directory present is honoured, whatever DOCKER_HEAVY_SLOTS says; that setting only limits the slots this
# copy takes. A holder without a group line (an older copy) is grouped by its PID's working directory, else its label.
# A ticket without a group line belongs to an older single-slot copy, which only ever takes slot 1: later tickets may
# take slots 2..N past it. Entries of another user, symlinks and directories holding more than an owner file are never
# removed. Labels and groups lose their control characters before they are written; numeric fields are validated
# before use, so a malformed owner file is judged by its age only, never as "pid gone".
set -u
set -f

VARIANT_IGNORE_OWN_REPO=1

PROG=$(basename "$0")

usage() {
  cat <<USAGE
Usage: $PROG acquire <owner>          wait for the turn, a free slot for the group and the load / memory gates
       $PROG release [<pid>]          release the slot held by <pid> (default: the parent process, else the oldest
                                      slot held without a PID)
       $PROG status                   slots, group tokens, queue, load average and available memory
       $PROG run <owner> <cmd> [...]  acquire, run <cmd>, always release; exit code of <cmd>

<owner> is a free-text label shown by status (e.g. "my-app: release-check"). Its prefix before ":" is the group of last
resort. Control characters (newlines included) are replaced by spaces.

Environment:
  DOCKER_HEAVY_LOCK_DIR     slot 1 directory; slot N is <dir>.N (default: /tmp/docker-heavy.lock)
  DOCKER_HEAVY_QUEUE_DIR    queue directory (default: /tmp/docker-heavy.queue)
  DOCKER_HEAVY_GROUP_DIR    group token directory (default: the lock directory with .lock replaced by .group)
  DOCKER_HEAVY_STALE_LOG    log of removed stale locks (default: /tmp/docker-heavy.stale.log; never a symlink)
  DOCKER_HEAVY_SLOTS        slots this copy may take (default: 4); every existing slot is honoured
  DOCKER_HEAVY_MAXLOAD      hand out a slot only below this 1-minute load average (default: MAXLOAD or 10)
  DOCKER_HEAVY_MIN_MEM_MB   hand out a slot only with this much MemAvailable, in MB (default: 6144; 0 disables; an
                            unknown MemAvailable keeps the gate closed)
  DOCKER_HEAVY_GROUP        group of this job (default: the git repository of the current directory, else the label
                            prefix before ":"); at most one job per group holds a slot
  DOCKER_HEAVY_IGNORE_REPOS space-separated repository names never used as a group (e.g. a workspace that runs no stack)
  DOCKER_HEAVY_POLL         seconds between checks while waiting (default: POLL or 10)
  DOCKER_HEAVY_LOCK_MAXAGE  seconds after which a holder without a valid PID is stale (default: 3600)
  DOCKER_HEAVY_PRIORITY     1: queue before every normal ticket (never interrupts a holder)
  DOCKER_HEAVY_LOCK_PID     long-lived PID recorded by a bare acquire (run records its own)
  DOCKER_HEAVY_LOCK_HELD    set by run for <cmd>: nested acquire / release / run are no-ops
An invalid number falls back to its default with a warning.

A holder whose PID is gone (killed job, closed session) is removed by a waiter and logged.
Exit codes: 0 success (run: the exit code of <cmd>), 1 acquire failed, 2 usage error.
USAGE
}

warn() { echo "$PROG: $*" >&2; }

# Replaces control characters (CR, LF, ESC, ...) by spaces (33 of them: one per character of the first set).
clean() { printf '%s' "$1" | tr '\000-\037\177' '                                 '; }

is_uint() { case "$1" in ''|*[!0-9]*) return 1 ;; esac; }
is_count() { is_uint "$1" && case "$1" in 0*) return 1 ;; esac; }
is_decimal() { case "$1" in ''|.*|*.|*.*.*|*[!0-9.]*) return 1 ;; esac; }
is_poll() { is_decimal "$1" && awk -v p="$1" 'BEGIN{exit !(p + 0 > 0)}'; }
# A PID worth checking: an integer above 1 (0, 1 and -1 would match the caller's process group, init or everything).
valid_pid() { is_count "$1" && [ "$1" -gt 1 ]; }

# setting <name> <value> <default> <check>: the value when <check> accepts it, else the default with a warning.
setting() {
  if "$4" "$2"; then echo "$2"; else warn "ignoring $1=\"$(clean "$2")\" (not a valid number); using $3"; echo "$3"; fi
}

case "${1:-}" in
  -h|--help) usage; exit 0 ;;
esac

L=${DOCKER_HEAVY_LOCK_DIR:-/tmp/docker-heavy.lock}
while :; do case "$L" in ?*/) L=${L%/} ;; *) break ;; esac; done
Q=${DOCKER_HEAVY_QUEUE_DIR:-/tmp/docker-heavy.queue}
GD=${DOCKER_HEAVY_GROUP_DIR:-${L%.lock}.group}
STALE_LOG=${DOCKER_HEAVY_STALE_LOG:-/tmp/docker-heavy.stale.log}
SLOTS=$(setting DOCKER_HEAVY_SLOTS "${DOCKER_HEAVY_SLOTS:-4}" 4 is_count)
MAXLOAD=$(setting DOCKER_HEAVY_MAXLOAD "${DOCKER_HEAVY_MAXLOAD:-${MAXLOAD:-10}}" 10 is_decimal)
MINMEM_MB=$(setting DOCKER_HEAVY_MIN_MEM_MB "${DOCKER_HEAVY_MIN_MEM_MB:-6144}" 6144 is_uint)
MAXAGE=$(setting DOCKER_HEAVY_LOCK_MAXAGE "${DOCKER_HEAVY_LOCK_MAXAGE:-3600}" 3600 is_uint)
POLL=$(setting DOCKER_HEAVY_POLL "${DOCKER_HEAVY_POLL:-${POLL:-10}}" 10 is_poll)
IGNORE_REPOS=${DOCKER_HEAVY_IGNORE_REPOS:-}
LDIR=$(dirname "$L")
LBASE=$(basename "$L")
SELF=$0

if [ -n "${DOCKER_HEAVY_LOCK_HELD:-}" ]; then
  case "${1:-}" in
    acquire|release) echo "lock already held by an outer run (DOCKER_HEAVY_LOCK_HELD)"; exit 0 ;;
    run)
      [ "$#" -ge 3 ] || { echo "ERROR: run needs <owner> <cmd> (see --help)." >&2; exit 2; }
      shift 2; exec "$@" ;;
  esac
fi

now_ns() {
  ns=$(date +%s%N 2>/dev/null)
  case "$ns" in
    *N|'') printf '%s000000000\n' "$(date +%s)" ;;
    *) printf '%s\n' "$ns" ;;
  esac
}

load1() {
  cut -d' ' -f1 /proc/loadavg 2>/dev/null || uptime | sed -E 's/.*load averages?: *([0-9.]+).*/\1/'
}

# MemAvailable in MB; empty when the system does not expose it.
mem_available_mb() {
  awk '/^MemAvailable:/{print int($2/1024)}' /proc/meminfo 2>/dev/null
}

# True when the load and memory gates let a slot be handed out. An unknown load or MemAvailable keeps them closed.
gates_open() {
  load=$(load1)
  is_decimal "$load" || return 1
  awk -v l="$load" -v m="$MAXLOAD" 'BEGIN{exit !(l + 0 < m + 0)}' || return 1
  [ "$MINMEM_MB" -eq 0 ] && return 0
  mem=$(mem_available_mb)
  is_uint "$mem" || return 1
  [ "$mem" -ge "$MINMEM_MB" ]
}

line() { sed -n "$1p" "$2" 2>/dev/null; }

mtime() {
  m=$(stat -c %Y "$1" 2>/dev/null || stat -f %m "$1" 2>/dev/null)
  is_uint "$m" && echo "$m"
}

# Seconds since epoch $1 (validated by the caller).
age_of() { awk -v n="$(date +%s)" -v s="$1" 'BEGIN{printf "%d\n", n - s}'; }

# True when PID $1 runs. EPERM (another user's process) counts as running.
alive() {
  err=$(kill -0 "$1" 2>&1) && return 0
  case "$err" in *ermit*|*EPERM*) return 0 ;; esac
  return 1
}

# A directory of the current user that is not a symlink.
owned_dir() { [ -d "$1" ] && [ ! -L "$1" ] && [ -O "$1" ]; }

# True when lock directory $1 holds nothing but (at most) a regular owner file: the only shape this script removes.
lock_shaped() {
  c=$(ls -1A "$1" 2>/dev/null) || return 1
  [ -z "$c" ] && return 0
  [ "$c" = owner ] && [ -f "$1/owner" ] && [ ! -L "$1/owner" ]
}

# Removes lock directory $1 (an own slot or group token) after checking its shape.
drop() {
  owned_dir "$1" || return 1
  lock_shaped "$1" || { warn "not removing $1: it holds more than an owner file"; return 1; }
  rm -f "$1/owner"
  rmdir "$1" 2>/dev/null
}

# Removes stale lock directory $1 whose owner file read $2 when it was judged. It is renamed first; if the renamed
# directory is not the one judged (a fresh holder took the path meanwhile), it is put back, never deleted.
reap() {
  owned_dir "$1" || { warn "not removing $1: not a lock directory of this user"; return 1; }
  lock_shaped "$1" || { warn "not removing $1: it holds more than an owner file"; return 1; }
  r="$1.reap.$$.$(now_ns)"
  mv "$1" "$r" 2>/dev/null || return 1
  if [ "$(cat "$r/owner" 2>/dev/null)" != "$2" ]; then
    [ -e "$1" ] || mv "$r" "$1" 2>/dev/null
    [ -e "$r" ] && warn "left $r aside: a fresh lock was taken while a stale one was being removed"
    return 1
  fi
  rm -f "$r/owner"
  rmdir "$r" 2>/dev/null
}

log_stale() {
  echo "$1" >&2
  if [ -L "$STALE_LOG" ] || { [ -e "$STALE_LOG" ] && [ ! -O "$STALE_LOG" ]; }; then
    warn "not writing $STALE_LOG: a symlink or another user's file"
  else
    echo "$1" >> "$STALE_LOG" 2>/dev/null
  fi
}

slot_path() {
  if [ "$1" -eq 1 ]; then echo "$L"; else echo "$L.$1"; fi
}

# Numbers of every existing slot directory ($L is 1, $L.<n> is n), whatever DOCKER_HEAVY_SLOTS is.
slots_all() {
  for e in $(ls -1A "$LDIR" 2>/dev/null); do
    case "$e" in
      "$LBASE") [ -d "$L" ] && echo 1 ;;
      "$LBASE".*) n=${e#"$LBASE".}; is_count "$n" && [ "$n" -ge 2 ] && [ -d "$LDIR/$e" ] && echo "$n" ;;
    esac
  done | sort -n
}

# Group of directory $1: the main repository's directory name (git common dir), so the worktrees of one repository
# agree (a separate clone has its own .git and so its own group). Fails outside a repository.
group_of_dir() {
  c=$(cd "$1" 2>/dev/null && c=$(git rev-parse --git-common-dir 2>/dev/null) && cd "$c" 2>/dev/null && pwd -P) || return 1
  case "$c" in */.git) c=${c%/.git} ;; esac
  basename "$c"
}

OWN_REPO=
if [ "$VARIANT_IGNORE_OWN_REPO" = 1 ]; then
  OWN_REPO=$(group_of_dir "$(dirname "$(readlink -f "$0" 2>/dev/null || echo "$0")")") || OWN_REPO=
fi

# Group of directory $1, unless it is the repository holding this copy (workspace variant) or an ignored name.
dir_group() {
  g=$(group_of_dir "$1") || return 1
  [ -n "$OWN_REPO" ] && [ "$g" = "$OWN_REPO" ] && return 1
  for i in $IGNORE_REPOS; do
    [ "$i" = "$g" ] && return 1
  done
  echo "$g"
}

# Label prefix before ":" (or its first word).
label_group() {
  g=${1%%:*}
  echo "${g%% *}"
}

# Group of the holder of slot directory $1: line 5; else the holder PID's working directory; else its label prefix.
holder_group() {
  hg=$(line 5 "$1/owner")
  if [ -n "$hg" ]; then echo "$hg"; return 0; fi
  hp=$(line 3 "$1/owner")
  if valid_pid "$hp" && hg=$(dir_group "/proc/$hp/cwd"); then echo "$hg"; return 0; fi
  label_group "$(line 1 "$1/owner")"
}

# Prints a reason and returns 0 when entry $1 (slot or token) whose PID line is $2 and epoch line is $3 is stale. Only a
# valid PID can be "gone"; without one (bare acquire, malformed or unwritten owner file) only the age counts.
judge() {
  if valid_pid "$2"; then
    alive "$2" && return 1
    echo "pid $2 gone"
    return 0
  fi
  since=$3
  is_count "$since" || since=$(mtime "$1") || return 1
  age=$(age_of "$since")
  [ "$age" -ge "$MAXAGE" ] || return 1
  echo "held $(awk -v a="$age" 'BEGIN{printf "%d", a / 60}') min without a live PID"
  return 0
}

# Prints a reason and returns 0 when the holder of slot directory $1 is stale. Another user's slot is never stale.
holder_stale() {
  owned_dir "$1" || return 1
  judge "$1" "$(line 3 "$1/owner")" "$(line 4 "$1/owner")"
}

token_path() {
  n=$(printf '%s' "$1" | tr -c 'A-Za-z0-9._-' '_' | cut -c1-64)
  c=$(printf '%s' "$1" | cksum | cut -d' ' -f1)
  echo "$GD/$n-$c"
}

# Prints a reason and returns 0 when group token $1 is stale: its PID is gone; or, without a PID (bare acquire), its
# slot is gone or held by another group (after a 60 s grace), or it is older than the maximum age.
token_stale() {
  owned_dir "$1" || return 1
  tpid=$(line 2 "$1/owner")
  if [ "$tpid" = "-" ]; then
    tslot=$(line 4 "$1/owner")
    tsince=$(line 3 "$1/owner")
    is_count "$tsince" || tsince=$(mtime "$1") || return 1
    if is_count "$tslot"; then
      ts=$(slot_path "$tslot")
      if [ ! -d "$ts" ] || [ "$(holder_group "$ts")" != "$(line 1 "$1/owner")" ]; then
        [ "$(age_of "$tsince")" -ge 60 ] && { echo "slot $tslot released"; return 0; }
      fi
    fi
  fi
  judge "$1" "$tpid" "$(line 3 "$1/owner")"
}

# True when a live (not stale) slot holder has group $1.
slots_group_busy() {
  for b in $(slots_all); do
    bs=$(slot_path "$b")
    [ "$(holder_group "$bs")" = "$1" ] || continue
    holder_stale "$bs" >/dev/null || return 0
  done
  return 1
}

# True when group $1 holds a live group token or slot.
group_busy() {
  bt=$(token_path "$1")
  if [ -e "$bt" ] || [ -L "$bt" ]; then
    token_stale "$bt" >/dev/null || return 0
  fi
  slots_group_busy "$1"
}

# Prints the PID of ticket name $1 when it is [0-]<digits>-<pid> with a PID above 1.
ticket_pid() {
  rest=${1#0-}
  tp=${rest##*-}
  ts=${rest%-*}
  [ "$ts" != "$rest" ] && is_uint "$ts" && valid_pid "$tp" && echo "$tp"
}

# Own regular files of the queue with a ticket name, oldest first. Anything else there is ignored, never removed.
tickets() {
  for t in $(ls -1A "$Q" 2>/dev/null | sort); do
    ticket_pid "$t" >/dev/null && [ -f "$Q/$t" ] && [ ! -L "$Q/$t" ] && [ -O "$Q/$t" ] && echo "$t"
  done
}

prune_stale() {
  owned_dir "$Q" || return 0
  for t in $(tickets); do
    alive "$(ticket_pid "$t")" || rm -f "$Q/$t"
  done
}

# Prints the first slot number this ticket may take; fails while it has to wait for older tickets. The first ticket
# may take any slot. A later ticket may pass tickets whose group already holds a slot (they cannot start anyway), and
# may take slots 2..N past tickets without a group line (older single-slot copies, which only ever take slot 1).
first_slot() {
  from=
  for t in $(tickets); do
    if [ "$Q/$t" = "$ticket" ]; then
      echo "${from:-1}"
      return 0
    fi
    tg=$(line 2 "$Q/$t")
    if [ -z "$tg" ]; then from=2; continue; fi
    group_busy "$tg" && continue
    return 1
  done
  return 1
}

# Removes the own group tokens whose PID line is $1 (and, when $2 is set, whose slot line is $2).
drop_tokens() {
  for e in $(ls -1A "$GD" 2>/dev/null); do
    tp="$GD/$e"
    owned_dir "$tp" || continue
    [ "$(line 2 "$tp/owner")" = "$1" ] || continue
    [ -z "${2:-}" ] || [ "$(line 4 "$tp/owner")" = "$2" ] || continue
    drop "$tp"
  done
}

# Creates directory $1 if needed; exits when it is a symlink or another user's.
need_own_dir() {
  if [ -L "$1" ] || { [ -e "$1" ] && ! owned_dir "$1"; }; then
    warn "$1 is a symlink or belongs to another user; refusing to use it (set $2)"
    exit 1
  fi
  mkdir -p "$1" 2>/dev/null && owned_dir "$1" || { warn "cannot create $1"; exit 1; }
}

case "${1:-}" in
  run)
    [ "$#" -ge 3 ] || { echo "ERROR: run needs <owner> <cmd> (see --help)." >&2; exit 2; }
    owner=$2
    shift 2
    DOCKER_HEAVY_LOCK_PID=$$ sh "$SELF" acquire "$owner" || exit 1
    trap 'sh "$SELF" release $$ >/dev/null' EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM
    DOCKER_HEAVY_LOCK_HELD=1 "$@"
    exit $? ;;
  acquire)
    [ -n "${2:-}" ] || { echo "ERROR: acquire needs <owner> (see --help)." >&2; exit 2; }
    owner=$(clean "$2")
    group=$(clean "${DOCKER_HEAVY_GROUP:-}")
    [ -n "$group" ] || group=$(dir_group "$PWD") || group=$(label_group "$owner")
    [ -n "$group" ] || group=default
    mypid=${DOCKER_HEAVY_LOCK_PID:--}
    valid_pid "$mypid" || mypid=-
    need_own_dir "$Q" DOCKER_HEAVY_QUEUE_DIR
    need_own_dir "$GD" DOCKER_HEAVY_GROUP_DIR
    ticket="$Q/${DOCKER_HEAVY_PRIORITY:+0-}$(now_ns)-$$"
    printf '%s\n%s\n' "$owner" "$group" > "$ticket" || exit 1
    tok=$(token_path "$group")
    pending=
    trap 'rm -f "$ticket"; [ -z "$pending" ] || drop "$pending"' EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM
    hinted=
    while :; do
      prune_stale
      # A ticket removed meanwhile is written again under the same name, so the waiter keeps its place.
      [ -f "$ticket" ] || printf '%s\n%s\n' "$owner" "$group" > "$ticket"
      # A stale token of this group is cleared by this group's waiters only, before the group check.
      if reason=$(token_stale "$tok"); then
        old=$(cat "$tok/owner" 2>/dev/null)
        reap "$tok" "$old" && log_stale "$(date -u +%FT%TZ) stale group token of \"$group\" removed by \"$owner\" ($reason)"
      fi
      if from=$(first_slot) && ! group_busy "$group"; then
        if ! gates_open; then
          if [ -z "$hinted" ] && [ "$MINMEM_MB" -ne 0 ] && ! is_uint "$(mem_available_mb)"; then
            warn "MemAvailable is unknown on this system, so the memory gate stays closed (set DOCKER_HEAVY_MIN_MEM_MB=0)"
            hinted=1
          fi
        elif mkdir "$tok" 2>/dev/null; then
          # The group token is ours: no other job of this group gets past this point until it is removed.
          pending=$tok
          printf '%s\n%s\n%s\n\n' "$group" "$mypid" "$(date +%s)" > "$tok/owner"
          # A holder of this group written by an older copy (no token) still counts.
          if ! slots_group_busy "$group"; then
            n=$from
            while [ "$n" -le "$SLOTS" ]; do
              s=$(slot_path "$n")
              # Only a waiter whose turn it is clears a stale holder, right before trying to take the slot itself.
              if reason=$(holder_stale "$s"); then
                old=$(cat "$s/owner" 2>/dev/null)
                if reap "$s" "$old"; then
                  log_stale "$(date -u +%FT%TZ) stale lock of \"$(clean "$(printf '%s\n' "$old" | sed -n 1p)")\" (slot $n) removed by \"$owner\" ($reason)"
                fi
              fi
              if mkdir "$s" 2>/dev/null; then
                printf '%s\n%s\n%s\n%s\n%s\n' "$owner" "$(date -u +%FT%TZ)" "$mypid" "$(date +%s)" "$group" > "$s/owner"
                printf '%s\n%s\n%s\n%s\n' "$group" "$mypid" "$(date +%s)" "$n" > "$tok/owner"
                pending=
                echo "acquired by $owner (slot $n/$SLOTS, group $group)"
                exit 0
              fi
              n=$((n + 1))
            done
          fi
          drop "$tok"
          pending=
        fi
      fi
      sleep "$POLL"
    done ;;
  release)
    want=${2:-$PPID}
    valid_pid "$want" || { echo "ERROR: release needs a PID above 1 (got \"$(clean "$want")\")." >&2; exit 2; }
    for n in $(slots_all); do
      s=$(slot_path "$n")
      owned_dir "$s" || continue
      if [ "$(line 3 "$s/owner")" = "$want" ]; then
        drop "$s"
        drop_tokens "$want"
        echo "released slot $n"
        exit 0
      fi
    done
    drop_tokens "$want"
    # An explicit PID without a slot (e.g. run interrupted before it got one) never frees someone else's slot.
    if [ -n "${2:-}" ]; then echo "nothing to release"; exit 0; fi
    # No slot held by the parent process: the oldest own slot held without a PID (a bare acquire).
    oldest=
    oldest_at=
    for n in $(slots_all); do
      s=$(slot_path "$n")
      owned_dir "$s" && [ "$(line 3 "$s/owner")" = "-" ] || continue
      at=$(line 4 "$s/owner")
      is_count "$at" || at=$(mtime "$s") || continue
      if [ -z "$oldest" ] || [ "$at" -lt "$oldest_at" ]; then oldest=$n; oldest_at=$at; fi
    done
    if [ -n "$oldest" ]; then
      drop "$(slot_path "$oldest")"
      drop_tokens - "$oldest"
      echo "released $(basename "$(slot_path "$oldest")")"
    else
      echo "nothing to release"
    fi ;;
  status)
    held=0
    for n in $(slots_all); do
      s=$(slot_path "$n")
      held=$((held + 1))
      if ! owned_dir "$s"; then
        echo "held (slot $n/$SLOTS): a symlink or another user's directory (never removed)"
        continue
      fi
      pid=$(clean "$(line 3 "$s/owner")")
      if reason=$(holder_stale "$s"); then st=" (stale: $reason)"; else st=; fi
      echo "held (slot $n/$SLOTS, group $(clean "$(holder_group "$s")")): $(clean "$(line 1 "$s/owner")") since $(clean "$(line 2 "$s/owner")") pid ${pid:--}$st"
    done
    [ "$held" -gt 0 ] || echo "free (0/$SLOTS slots held)"
    # Group tokens without a live slot of their group (a waiter between token and slot, or one left behind).
    for e in $(ls -1A "$GD" 2>/dev/null); do
      tp="$GD/$e"
      [ -d "$tp" ] || continue
      tg=$(line 1 "$tp/owner")
      [ -n "$tg" ] && slots_group_busy "$tg" && continue
      if reason=$(token_stale "$tp"); then st=" (stale: $reason)"; else st=; fi
      echo "group token without a slot: $(clean "$tg") pid $(clean "$(line 2 "$tp/owner")")$st"
    done
    prune_stale 2>/dev/null
    list=$(tickets)
    if [ -n "$list" ]; then
      echo "queue (oldest first):"
      for t in $list; do echo "  $(clean "$(line 1 "$Q/$t")") [group $(clean "$(line 2 "$Q/$t")")] [$t]"; done
    fi
    mem=$(mem_available_mb)
    echo "load: $(cut -d' ' -f1-3 /proc/loadavg 2>/dev/null || load1) (max $MAXLOAD), mem available: ${mem:-unknown} MB (min $MINMEM_MB)" ;;
  *) echo "ERROR: expected acquire <owner> | release [<pid>] | status | run <owner> <cmd...> (see --help)." >&2; exit 2 ;;
esac
