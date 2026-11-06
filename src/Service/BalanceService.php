<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Exception\CustomerNotFound;
use App\Model\BalanceUpdate;
use App\Model\Customer;
use App\Repository\CustomerRepository;
use App\Repository\TransactionRepository;
use App\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

final readonly class BalanceService
{
    public const int BONUS_EVERY_NTH_DEPOSIT = 3;

    public function __construct(
        private CustomerRepository $customers,
        private TransactionRepository $ledger,
        private TransactionManager $transactions,
    ) {
    }

    /**
     * Credits a customer's real balance with a deposit.
     * Every 3rd successful deposit also credits a bonus percentage to the bonus balance.
     *
     * @throws CustomerNotFound
     */
    public function deposit(Uuid $customerId, Money $amount): BalanceUpdate
    {
        return $this->transactions->transactional(function () use ($customerId, $amount): BalanceUpdate {
            $customer = $this->customers->findForUpdate($customerId) ?? throw CustomerNotFound::withId($customerId);
            $bonus = $this->calculateDepositBonus($customer, $amount);
            $transaction = $this->ledger->deposit($customer, $amount, $bonus);
            $customer = $customer->applyDeposit($transaction);
            $this->customers->updateBalances($customer->id, ['realBalance' => $customer->realBalance, 'bonusBalance' => $customer->bonusBalance]);

            return new BalanceUpdate($transaction, $customer);
        });
    }

    private function calculateDepositBonus(Customer $customer, Money $amount): Money
    {
        // a rejected deposit rolls back and leaves no ledger row, so it never counts
        $depositNumber = $this->ledger->countDeposits($customer->id) + 1;

        if (0 !== $depositNumber % self::BONUS_EVERY_NTH_DEPOSIT) {
            return Money::zero();
        }

        return $amount->percent($customer->bonusPercent);
    }
}
