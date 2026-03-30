<?php
/**
 * Banking Business Patterns
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class BankingPatterns extends AbstractBusinessPattern
{
    public static function get_patterns(): array
    {
    $base = static::base();

    $base['sales']['high'] = array_merge($base['sales']['high'], [
        // Accounts
        'savings account', 'current account', 'checking account', 'business account',
        'salary account', 'student account', 'joint account', 'foreign currency account',
        'account opening', 'open new account', 'zero balance account',

        // Cards
        'credit card', 'debit card', 'prepaid card', 'virtual card',
        'card upgrade', 'new card request', 'card rewards', 'cashback card',
        'travel card', 'business credit card',

        // Loans & Financing
        'personal loan', 'home loan', 'mortgage loan', 'car loan', 'auto loan',
        'student loan', 'education loan', 'business loan', 'working capital loan',
        'loan eligibility', 'loan quote', 'loan offer', 'loan pre-approval',
        'interest rate', 'apr', 'emi', 'emi plan',

        // Deposits & Investments
        'fixed deposit', 'term deposit', 'certificate of deposit', 'recurring deposit',
        'investment account', 'mutual fund', 'retirement account', 'pension account',
        'wealth management', 'private banking', 'portfolio management',

        // Payments & Commercial Banking
        'merchant services', 'point of sale terminal', 'pos machine', 'payment gateway',
        'trade finance', 'letter of credit', 'bank guarantee', 'invoice financing',
        'international transfer', 'remittance service', 'forex service'
    ]);

    $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], [
        'best account for me', 'compare account types', 'banking package',
        'rate comparison', 'fee comparison', 'service charges',
        'looking for better rate', 'switch bank', 'move salary account',
        'co applicant loan', 'balance transfer', 'debt consolidation',
        'priority banking', 'relationship manager', 'corporate account',
        'treasury services', 'payroll services', 'cash management'
    ]);

    $base['support']['high'] = array_merge($base['support']['high'], [
        // Account/Card Access Issues
        'account blocked', 'account frozen', 'account locked', 'unable to login',
        'card blocked', 'card not working', 'card declined', 'pin not working',
        'otp not received', '2fa issue',

        // Transaction Issues
        'failed transaction', 'declined transaction', 'reversed transaction',
        'amount debited but failed', 'double debit', 'duplicate transaction',
        'charge dispute', 'chargeback request', 'unauthorized transaction',
        'pending transaction', 'transaction stuck',

        // Digital/ATM Issues
        'online banking not working', 'internet banking down',
        'mobile app not working', 'bank app crash', 'payment app issue',
        'atm issue', 'cash not dispensed', 'cash stuck in atm', 'atm retained card',
        'deposit machine issue',

        // Fraud & Compliance
        'fraud alert', 'suspicious transaction', 'account takeover',
        'phishing alert', 'identity theft', 'kyc update', 'kyc rejected',
        'account verification issue', 'compliance hold', 'sanctions screening hold',

        // Transfers/Payments
        'wire transfer failed', 'swift transfer failed', 'iban issue',
        'beneficiary not added', 'beneficiary activation failed',
        'payment reversal failed', 'refund not credited'
    ]);

    $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], [
        'statement request', 'bank statement download', 'transaction history missing',
        'cheque book request', 'stop cheque', 'cheque bounce concern',
        'card replacement request', 'address update', 'phone number update',
        'email update', 'nominee update', 'limit change request',
        'loan account statement', 'interest certificate', 'tax certificate',
        'branch appointment', 'rm call back', 'service request status'
    ]);

    $base['feedback']['high'] = array_merge($base['feedback']['high'] ?? [], [
        'faster onboarding', 'paperless account opening', 'seamless kyc process',
        'better branch service', 'queue management', 'extended branch hours',
        'improve app usability', 'improve transfer speed', 'better notification system',
        'personalized offers', 'better wealth advisory', 'clear fee disclosure',
        'transparent loan terms', 'faster loan approval workflow',
        'better dispute resolution', 'stronger fraud protection',
        'better chatbot support', 'multilingual banking support'
    ]);

    $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], [
        'branch experience', 'relationship manager', 'digital banking experience',
        'faster transfers', 'better mobile banking', 'better internet banking',
        'lower fees', 'clearer charges', 'improve turnaround time',
        'more atm locations', 'better customer hotline', 'faster callback',
        'simpler loan documentation', 'better card controls'
    ]);

    $base['complaint']['high'] = array_merge($base['complaint']['high'], [
        'wrong debit', 'double charged', 'hidden charges', 'excessive fee',
        'unfair interest', 'payment reversal failed', 'chargeback denied',
        'poor banking service', 'branch staff rude', 'complaint to regulator',
        'mis-sold loan', 'mis-sold credit card', 'unauthorized loan insurance',
        'loan harassment', 'recovery harassment', 'harassing calls',
        'account closed without notice', 'unjustified account freeze',
        'refused dispute', 'complaint ignored', 'ombudsman complaint',
        'privacy breach', 'bank data leak', 'sensitive data exposed'
    ]);

    $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], [
        'slow customer service', 'long call wait time', 'branch wait too long',
        'unclear charges', 'fee not explained', 'statement confusing',
        'delayed loan decision', 'delayed card delivery', 'delayed account activation',
        'repeated documentation requests', 'poor communication', 'unhelpful support'
    ]);

    $base['question']['high'] = array_merge($base['question']['high'] ?? [], [
        'how to open account', 'documents required for account', 'minimum balance',
        'loan processing time', 'emi calculator', 'credit score requirement',
        'how to increase credit limit', 'how to activate card', 'swift code',
        'what is iban', 'how to apply for loan', 'what is foreclosure charge',
        'how to close account', 'how to unblock card', 'how to dispute charge',
        'how to reset netbanking password', 'how to enable international usage',
        'what is daily transfer limit', 'what is cash withdrawal limit'
    ]);

    $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], [
        'which account is best', 'difference between savings and current account',
        'eligibility criteria', 'required income', 'collateral requirement',
        'processing fee', 'prepayment penalty', 'late payment charge',
        'how long for card delivery', 'how to link account to app',
        'how to update kyc', 'branch timings', 'holiday hours'
    ]);

    $base['spam']['high'] = array_merge($base['spam']['high'] ?? [], [
        'verify bank account now', 'urgent kyc update link', 'suspend account immediately',
        'share otp', 'share pin', 'share cvv', 'share internet banking password',
        'account will be blocked click here', 'fake bank alert', 'winner of bank lottery',
        'quick loan guaranteed approval', 'instant loan no documents',
        'phishing bank message', 'credential harvesting', 'bank impersonation'
    ]);

    return $base;
    }
}